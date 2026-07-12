# AgentTag

AgentTag is a self-hosted Symfony bot that delegates Mattermost threads to Codex. Each thread gets an isolated persistent workspace and one durable task whose status card evolves from acknowledgement through completion.

## What it does

- Accepts `@Codex` requests from a Mattermost outgoing webhook.
- Immediately uses a cheap low-reasoning model to write a short acknowledgement and task title in the user’s language.
- Creates one Mattermost task card and updates it instead of streaming commands or harness events.
- Offers Cancel, Retry, Resume, Discard, and Show technical log buttons.
- Treats new messages during a task as steering for the same Codex session.
- Persists Codex session UUIDs and resumes them after steering, scheduled wakeups, retries, or worker restarts.
- Supports waiting for CI, reviews, schedules, and other external state through durable Messenger wakeups.
- Stores bounded retry policies, task deadlines, notification preferences, redacted logs, events, artifacts, and token usage.
- Runs different Mattermost threads concurrently while serializing work within one thread.

AgentTag is Mattermost-only. Slack, global memory, approvals, and the web admin panel have intentionally been removed.

## Requirements

- PHP 8.4, Composer, PostgreSQL 16 or compatible.
- Codex CLI installed and authenticated for the worker’s Unix user.
- A Mattermost bot token and outgoing webhook token.
- One Symfony web process, one high-priority acknowledgement worker, and one or more task workers.
- A workspace template containing your `AGENTS.md`, skills, plugins, and shared documentation.

## Configuration

Put production secrets in `.env.local`, never in committed files.

```dotenv
APP_ENV=prod
APP_SECRET=change-this
DEFAULT_URI=https://agentag.example.com
DATABASE_URL="postgresql://app:change-this@127.0.0.1:5432/agentag?serverVersion=16&charset=utf8"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

AGENTAG_TAG=@Codex
AGENTAG_WORKSPACE_PATH=/srv/agentag/workspace
AGENTAG_CONTEXT_MAX_CHARS=12000
AGENTAG_RUN_TIMEOUT_SECONDS=1200
AGENTAG_REDACTION_PATTERNS=

AGENTAG_ACK_MODEL=gpt-5.6-luna
AGENTAG_ACK_TIMEOUT_SECONDS=20
AGENTAG_TASK_DEADLINE_SECONDS=86400
AGENTAG_MAX_RETRIES=2
AGENTAG_RETRY_DELAY_SECONDS=60
AGENTAG_NOTIFICATION_PREFERENCE=milestones

MATTERMOST_WEBHOOK_TOKEN=change-this
MATTERMOST_BASE_URL=https://mattermost.example.com
MATTERMOST_BOT_TOKEN=change-this
MATTERMOST_RECENT_REPLY_LIMIT=20
```

`DEFAULT_URI` must be the public AgentTag origin. Mattermost uses it to call `/integrations/mattermost/action` when a user clicks a task-card button. If AgentTag is on a private address, allow that address in Mattermost’s `AllowedUntrustedInternalConnections` setting.

`AGENTAG_NOTIFICATION_PREFERENCE` accepts `all`, `milestones`, or `completion`. Users can override it per task with phrases such as “notify me only when complete” or “notify me on every update.” A request can set a shorter deadline with “deadline in 3 hours” (minutes, hours, and days are supported).

The cheap acknowledgement call uses Codex with `--ephemeral`, `gpt-5.6-luna`, and low reasoning. If it times out or fails, AgentTag posts a deterministic acknowledgement and still queues the main task.

## Workspace layout

`AGENTAG_WORKSPACE_PATH` is a template. AgentTag copies it once per Mattermost thread and deliberately excludes `.git`.

```text
/srv/agentag/
  app/                         # this checkout
  workspace/                   # operator-managed template
    AGENTS.md
    skills/
    .codex-plugin/
    docs/
  runs/session-<hash>/         # persistent workspace per thread
  artifacts/run-<id>/          # Codex outputs
```

Repository URLs, safety policy, and domain knowledge belong in the template’s `AGENTS.md`, not application code.

## Mattermost setup and usage

Configure an outgoing webhook that sends matching posts to:

```text
POST https://agentag.example.com/integrations/mattermost/webhook
```

The bot token must be able to read the thread, create and edit its own posts, show typing, and join public channels where it is invoked. Interactive actions are signed with `APP_SECRET`, kept in Mattermost’s server-side action context, and restricted to the original requester.

Examples:

```text
@Codex fix the billing tests and watch CI
@Codex focus on the backend, ignore the UI
@Codex use repository foo instead
@Codex stop
@Codex retry
@Codex retry from the test step
```

While a task is running or waiting, a new mentioned message becomes steering for that same task rather than a replacement run. Stop preserves the workspace for 24 hours and exposes Resume and Discard buttons. Details returns the redacted technical log ephemerally; raw command events never appear in the status card.

Codex can keep a task alive by ending a stage with this private protocol (the comment is removed before display):

```html
<!-- agentag:{"action":"wait","seconds":300,"reason":"Waiting for CI"} -->
```

AgentTag schedules the same run, then invokes `codex exec resume <session-uuid>` at the wake time. Doctrine Messenger retains delayed messages and retries across process restarts. A dedicated `acknowledgements` transport keeps cheap-model acknowledgements ahead of long task executions.

## Development and verification

```bash
composer install
docker compose up -d database
composer check
```

Health endpoints:

```text
GET /health
GET /ready
```

Useful commands:

```bash
bin/console agentag:config:validate
bin/console agentag:workspace:cleanup --older-than-days=7
bin/console doctrine:migrations:migrate --no-interaction
```

Routine database inspection does not need custom maintenance commands:

```sql
SELECT id, status, title, attempt, wake_at, deadline_at, codex_thread_id
FROM agent_run ORDER BY id DESC LIMIT 50;

SELECT run_id, type, message, created_at
FROM run_event ORDER BY id DESC LIMIT 100;

DELETE FROM inbound_event WHERE received_at < NOW() - INTERVAL '30 days';
```

## Production

Host-based nginx, PHP-FPM, systemd worker, migration, and deployment instructions are in [prod/README.md](prod/README.md). Multiple `agentag-worker@N` instances can process independent threads concurrently.
