# AgentTag Production Host Setup

This directory contains a host-based production deployment for Ubuntu 24.04 with nginx and PHP-FPM. It intentionally does not include Docker production images and does not install PostgreSQL. Point `DATABASE_URL` at the PostgreSQL service you already run, for example through Coolify.

The nginx and PHP-FPM settings are adapted from the `openaction/docker-php` PHP 8.4 configs, but shaped for a normal host install:

- `prod/nginx/agentag.conf` -> `/etc/nginx/sites-available/agentag.conf`
- `prod/php/agentag.ini` -> `/etc/php/8.4/fpm/conf.d/99-agentag.ini`
- `prod/php-fpm/agentag.conf` -> `/etc/php/8.4/fpm/pool.d/agentag.conf`
- `prod/systemd/agentag-worker.service` -> `/etc/systemd/system/agentag-worker.service`

PHP-FPM runs web requests as `www-data`. The Messenger worker runs as `root`, so `codex exec` also runs as root on the host.

## 1. Install System Packages

Run as root or with `sudo`.

```bash
apt-get update
apt-get install -y software-properties-common ca-certificates curl gnupg unzip git openssh-client acl nginx
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y \
  php8.4-cli \
  php8.4-fpm \
  php8.4-apcu \
  php8.4-curl \
  php8.4-intl \
  php8.4-mbstring \
  php8.4-opcache \
  php8.4-pgsql \
  php8.4-readline \
  php8.4-xml \
  php8.4-zip
```

Install Composer:

```bash
EXPECTED_SIGNATURE="$(curl -fsSL https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
test "$EXPECTED_SIGNATURE" = "$ACTUAL_SIGNATURE"
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm /tmp/composer-setup.php
composer --version
```

Install Node.js and Codex for root:

```bash
curl -fsSL https://deb.nodesource.com/setup_24.x | bash -
apt-get install -y nodejs
npm install -g @openai/codex
codex --version
```

Authenticate Codex as root. Use the authentication method you normally use for the Codex CLI:

```bash
codex login
```

## 2. Deploy The Application

Create the runtime directories:

```bash
mkdir -p /srv/agentag/app /srv/agentag/workspace /srv/agentag/runs /srv/agentag/artifacts
```

Clone the app:

```bash
git clone git@github.com:openaction-codex/agentag.git /srv/agentag/app
cd /srv/agentag/app
```

Create the workspace template. Put your real `AGENTS.md`, skills, Codex plugins, MCP config, and shared docs here.

```bash
cat > /srv/agentag/workspace/AGENTS.md <<'EOF'
Answer in the user's language. Keep Mattermost updates concise. Ask for confirmation before pushing main, force pushing, deleting, overwriting, or other destructive changes.
Document available repositories and clone instructions here. Clone repositories into the session workspace when needed.
EOF
```

Create production environment overrides. Replace placeholders with real values. PostgreSQL is not installed by this guide; use the DSN from your existing PostgreSQL service.

```bash
install -m 0640 -o root -g www-data /dev/null /srv/agentag/app/.env.local
cat > /srv/agentag/app/.env.local <<'EOF'
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=change-this
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/agentag?serverVersion=16&charset=utf8"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

AGENTAG_TAG=@Codex
AGENTAG_WORKSPACE_PATH=/srv/agentag/workspace
AGENTAG_CONTEXT_MAX_CHARS=12000
AGENTAG_RUN_TIMEOUT_SECONDS=1200
AGENTAG_REDACTION_PATTERNS=
AGENTAG_ADMIN_USER=admin
AGENTAG_ADMIN_PASSWORD=change-this

MATTERMOST_WEBHOOK_TOKEN=change-this
MATTERMOST_BASE_URL=https://mattermost.example.com
MATTERMOST_BOT_TOKEN=change-this
MATTERMOST_RECENT_REPLY_LIMIT=20

SLACK_ENABLED=0
SLACK_VERIFICATION_TOKEN=
EOF
chown root:www-data /srv/agentag/app/.env.local
chmod 0640 /srv/agentag/app/.env.local
```

Install PHP dependencies. The `.env.local` file is already present, so Symfony auto-scripts use the production environment values.

```bash
cd /srv/agentag/app
APP_ENV=prod APP_DEBUG=0 composer install --no-dev --optimize-autoloader
```

Set write permissions. PHP-FPM needs write access for Symfony cache/logs and for session workspace preparation. The worker runs as root and can also write these paths.

```bash
mkdir -p /srv/agentag/app/var
chown -R root:www-data /srv/agentag
chown -R www-data:www-data /srv/agentag/app/var /srv/agentag/runs /srv/agentag/artifacts
find /srv/agentag -type d -exec chmod 0750 {} \;
find /srv/agentag -type f -exec chmod 0640 {} \;
chmod +x /srv/agentag/app/bin/console
```

Configure SSH for root if your workspace `AGENTS.md` asks Codex to clone private repositories.

```bash
mkdir -p /root/.ssh
chmod 0700 /root/.ssh
ssh-keyscan github.com >> /root/.ssh/known_hosts
chmod 0644 /root/.ssh/known_hosts
```

Add your deploy key or configure your SSH agent, then verify:

```bash
ssh -T git@github.com || true
```

## 3. Install Host Config

Copy the provided configuration:

```bash
cp /srv/agentag/app/prod/php/agentag.ini /etc/php/8.4/fpm/conf.d/99-agentag.ini
cp /srv/agentag/app/prod/php-fpm/agentag.conf /etc/php/8.4/fpm/pool.d/agentag.conf
cp /srv/agentag/app/prod/nginx/agentag.conf /etc/nginx/sites-available/agentag.conf
cp /srv/agentag/app/prod/systemd/agentag-worker.service /etc/systemd/system/agentag-worker.service
```

Edit the nginx hostname:

```bash
sed -i 's/agentag.example.com/YOUR_DOMAIN_HERE/g' /etc/nginx/sites-available/agentag.conf
```

Disable the default nginx site and enable AgentTag:

```bash
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/agentag.conf /etc/nginx/sites-enabled/agentag.conf
```

Validate config:

```bash
php8.4 -v
php8.4 -m | sort
php-fpm8.4 -t
nginx -t
```

## 4. Initialize The App

Run database migrations against your existing PostgreSQL service:

```bash
cd /srv/agentag/app
APP_ENV=prod APP_DEBUG=0 php8.4 bin/console doctrine:migrations:migrate --no-interaction
```

Warm Symfony cache and validate AgentTag configuration:

```bash
APP_ENV=prod APP_DEBUG=0 php8.4 bin/console cache:clear
APP_ENV=prod APP_DEBUG=0 php8.4 bin/console agentag:config:validate
APP_ENV=prod APP_DEBUG=0 php8.4 bin/console agentag:workspace:inspect
```

Start services:

```bash
systemctl daemon-reload
systemctl enable --now php8.4-fpm
systemctl restart php8.4-fpm
systemctl enable --now nginx
systemctl restart nginx
systemctl enable --now agentag-worker
```

Check service state and HTTP endpoints:

```bash
systemctl status php8.4-fpm --no-pager
systemctl status nginx --no-pager
systemctl status agentag-worker --no-pager
curl -i http://YOUR_DOMAIN_HERE/health
curl -i http://YOUR_DOMAIN_HERE/ready
```

Check logs:

```bash
journalctl -u agentag-worker -f
tail -f /var/log/nginx/agentag.error.log
tail -f /var/log/php8.4-fpm-agentag.slow.log
```

## 5. Mattermost

Configure a Mattermost outgoing webhook or integration to send requests to:

```text
http://YOUR_DOMAIN_HERE/integrations/mattermost/webhook
```

Use HTTPS if TLS is terminated on this nginx instance or upstream from it. Set the Mattermost token in `MATTERMOST_WEBHOOK_TOKEN`.

## 6. Deploy Updates

Pull code and update dependencies:

```bash
cd /srv/agentag/app
git pull --ff-only
APP_ENV=prod APP_DEBUG=0 composer install --no-dev --optimize-autoloader
APP_ENV=prod APP_DEBUG=0 php8.4 bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod APP_DEBUG=0 php8.4 bin/console cache:clear
```

Restart services so PHP-FPM OPcache and the worker see the new code:

```bash
systemctl restart php8.4-fpm
systemctl restart agentag-worker
nginx -t && systemctl reload nginx
```

Update `/srv/agentag/workspace` manually whenever you change your shared instructions, skills, Codex plugins, MCP config, or docs.

## 7. Cleanup Old Runtime Directories

Dry run first:

```bash
cd /srv/agentag/app
APP_ENV=prod APP_DEBUG=0 php8.4 bin/console agentag:workspace:cleanup --older-than-days=14
```

Delete matching old isolated workspaces/artifacts:

```bash
APP_ENV=prod APP_DEBUG=0 php8.4 bin/console agentag:workspace:cleanup --older-than-days=14 --force
```

Cleanup never deletes database run, session, memory, approval, or audit history.
