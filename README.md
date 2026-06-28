# AgentTag

AgentTag is a self-hosted Symfony bot for Mattermost first, Slack later. It responds to one configured instance tag, defaulting to `@Codex`, and runs versioned workflows with Codex CLI, local tools, MCP tools, and codebase access.

## Current Implementation Status

This repository is being implemented user story by user story from Linear `OPE-1100`. The current foundation provides:

- Symfony on PHP 8.4.
- PostgreSQL configuration through `DATABASE_URL`.
- Optional Redis-compatible Messenger/cache wiring through Symfony configuration when needed later.
- A configurable AgentTag workspace layout.
- A `/health` endpoint.
- PHPUnit, php-cs-fixer with Symfony rules, phpstan at max level, and GitHub Actions CI.

## Requirements

- PHP 8.4.
- Composer.
- PostgreSQL 16 or compatible.
- Git.
- Codex CLI installed and configured on the host before agent execution features are enabled.
- Mattermost bot credentials before Mattermost webhooks are enabled.

For local development, Docker Compose can start PostgreSQL:

```bash
docker compose up -d database
```

## Configuration

Copy environment overrides into `.env.local` on the target machine. Do not store production secrets in committed files.

```dotenv
APP_ENV=prod
APP_SECRET=change-this
DATABASE_URL="postgresql://app:change-this@127.0.0.1:5432/agentag?serverVersion=16&charset=utf8"

AGENTAG_TAG=@Codex
AGENTAG_WORKSPACE_PATH=/srv/agentag/workspace
AGENTAG_WORKFLOWS_PATH=/srv/agentag/workspace/workflows
AGENTAG_REPOSITORY_URLS=git@github.com:example/api.git,git@github.com:example/web.git
AGENTAG_ADMIN_USER=admin
AGENTAG_ADMIN_PASSWORD=change-this
```

The workflows repository is intentionally not hard-coded in AgentTag. Clone it yourself into the configured workspace path:

```bash
mkdir -p /srv/agentag/workspace
git clone <your-workflows-repository-ssh-url> /srv/agentag/workspace/workflows
```

The intended workspace layout is:

```text
/srv/agentag/workspace/
  workflows/              # manually cloned versioned workflows repository
  runs/<run-id>/           # isolated run workspaces
  runs/<run-id>/codebase/  # per-run repository clones
  cache/repositories/      # optional clone cache/mirrors
  artifacts/<run-id>/      # generated artifacts
```

Repositories available to the agent are configured through `AGENTAG_REPOSITORY_URLS`. They are expected to be SSH clone URLs usable by the local `git` CLI. Configure SSH keys on the VPS or local machine before enabling codebase workflows.

## Local Development

Install dependencies:

```bash
composer install
```

Run the development server:

```bash
symfony server:start
```

Check the app:

```bash
curl http://127.0.0.1:8000/health
```

Run the quality checks:

```bash
composer check
```

Individual checks:

```bash
composer test
composer phpstan
composer cs-check
```

Useful AgentTag console commands:

```bash
bin/console agentag:config:validate
bin/console agentag:workflows:list
bin/console agentag:repositories:list
bin/console agentag:tools:list
```

## VPS Setup

A practical VPS deployment is:

1. Install PHP 8.4, Composer, PostgreSQL, Git, and the Symfony CLI or your preferred PHP process manager.
2. Clone this repository to a release directory such as `/srv/agentag/app`.
3. Create `/srv/agentag/workspace` and clone your workflows repository to `/srv/agentag/workspace/workflows`.
4. Configure `.env.local` or real environment variables with `APP_SECRET`, `DATABASE_URL`, `AGENTAG_*`, Mattermost credentials, and later Linear/GitHub tokens as needed.
5. Run `composer install --no-dev --optimize-autoloader`.
6. Run Doctrine migrations once migrations exist.
7. Run the web process behind nginx/Caddy/Apache or Symfony CLI.
8. Run Symfony Messenger workers if asynchronous jobs are configured in later stories.
9. Protect logs and `.env.local` with normal server file permissions.

The read-only EasyAdmin panel will be added in a later story and protected by in-memory HTTP Basic credentials from `AGENTAG_ADMIN_USER` and `AGENTAG_ADMIN_PASSWORD`.

## Mattermost Usage Model

Mattermost support is implemented in later stories. The intended interaction model is:

- Mention `@Codex` in a root Mattermost message to start a new AgentTag session.
- Continue in the same Mattermost thread to keep the same session context.
- Start a new root message/thread for a new topic.
- Each substantial agent action inside the session creates its own isolated run workspace.

Global memory is explicit-only:

- The agent must ask before adding memory unless the user explicitly asks it to remember something.
- Users can list memories and delete a memory by ID once memory commands are implemented.
- Sensitive values are redacted or refused before storage.

## Review Discipline

Implementation should stay small and be reviewed regularly against:

- Functional correctness.
- Security and data safety.
- Performance and scalability.
- Reliability and operations.
- Readability and maintainability.
- Testing and validation.
