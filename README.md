# AgentTag

AgentTag is a self-hosted Symfony bot that delegates Mattermost threads to Codex. Each thread gets an isolated persistent workspace and one durable task whose status card evolves from acknowledgement through completion.

## What it does

- Accepts `@Codex` requests from a Mattermost outgoing webhook.
- Immediately uses GPT-5.6 Luna with max reasoning to write a short acknowledgement, task title, and model-routing decision in the user’s language.
- Creates one Mattermost task card and updates it instead of streaming commands or harness events.
- Renders the entire evolving task card as one blockquote so the separately posted answer is visually distinct.
- Shows one Stop button while work is active, keeps the completed step timeline, then posts the answer after it.
- Mirrors concrete Sol/Terra milestone notes into the task card as compact done/doing/next summaries while suppressing generic waiting updates.
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
AGENTAG_TASK_MODEL=gpt-5.6-luna
AGENTAG_TASK_REASONING_EFFORT=max
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

The acknowledgement call uses Codex with `--ephemeral`, `gpt-5.6-luna`, and max reasoning. It classifies the request into a persisted model route and writes a short rationale in the user's language. If it times out, fails, or returns an invalid route, AgentTag safely falls back to Luna with max reasoning and still queues the main task.

The main task runner explicitly pins `AGENTAG_TASK_MODEL` and `AGENTAG_TASK_REASONING_EFFORT`; it does not rely on root’s interactive Codex defaults. The default parent is GPT-5.6 Luna with max reasoning. The task card shows the selected model, reasoning effort, delegation role, and routing rationale before execution. Project-scoped custom agents under `.codex/agents/` provide Terra/max and Sol/xhigh specialist routes while the Luna parent remains responsible for coordination and the final answer.

When Codex reports a successful `spawn_agent` call, AgentTag records the child thread ID and inspects that child session's CLI metadata. The card shows a verified agent/model/reasoning line only when the actual role, model, and effort match the selected route; unavailable metadata or a mismatch is shown explicitly instead of being presented as verified. While the child runs, AgentTag tails only its user-facing milestone messages from the child rollout and mirrors them as `Sol —` or `Terra —` card stages. Workspace agent instructions keep those updates short, factual, and structured around what is done, currently happening, and next.

## Workspace layout

`AGENTAG_WORKSPACE_PATH` is a template. AgentTag copies it once per Mattermost thread and deliberately excludes `.git`.

```text
/srv/agentag/
  app/                         # this checkout
  workspace/                   # operator-managed template
    AGENTS.md
    .codex/agents/              # optional project-scoped delegated agents
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

While a task is running or waiting, a new mentioned message becomes steering for that same task rather than a replacement run. Stop interrupts a running command or immediately stops a task queued for retry, and preserves the workspace for 24 hours. Retry and resume remain available as explicit chat commands. Raw command events never appear in the status card.

Codex can keep a task alive by ending a stage with this private protocol (the comment is removed before display):

```html
<!-- agentag:{"action":"wait","seconds":300,"reason":"Waiting for CI"} -->
```

AgentTag schedules the same run, then invokes `codex exec resume <session-uuid>` at the wake time. Doctrine Messenger retains delayed messages and retries across process restarts. A dedicated `acknowledgements` transport keeps acknowledgement and routing inference ahead of long task executions.

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
