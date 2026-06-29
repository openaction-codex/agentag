# AgentTag

AgentTag is a self-hosted Symfony bot for Mattermost first, with thinner Slack support. It listens to one configured tag, `@Codex` by default, and runs one generic Codex-powered agent from a workspace template that you control.

The application code stays generic. Project knowledge, reusable skills, Codex plugins, MCP configuration, and operating instructions belong in the workspace template, usually in files such as `AGENTS.md`, `skills/`, `.codex-plugin/`, and shared docs.

## Status

The current foundation provides:

- Symfony on PHP 8.4 with PostgreSQL through `DATABASE_URL`.
- Mattermost and Slack webhook entrypoints with one session per chat thread.
- A generic agent profile backed by a workspace template directory.
- Per-session isolated workspaces copied from that template.
- Codex CLI execution in full-access mode with streamed JSON progress events.
- Mattermost typing indicators plus runner-generated progress/final messages.
- Explicit-only global memories with list and delete by ID.
- Approval requests for sensitive/destructive actions.
- Token usage storage on runs and sessions when the runner exposes usage.
- Read-only EasyAdmin pages under `/admin` for usage/debug inspection.
- PHPUnit, php-cs-fixer, phpstan, and GitHub Actions CI.

## Requirements

- PHP 8.4.
- Composer.
- PostgreSQL 16 or compatible.
- Git and OpenSSH client tools.
- Codex CLI installed and authenticated for the Unix user running workers.
- Local SSH configuration if your workspace instructions ask Codex to clone private repositories.
- Mattermost bot credentials for thread fetching, typing indicators, and progress posts.
- A web process for Symfony and at least one Symfony Messenger worker.

For local development, Docker Compose can start PostgreSQL:

```bash
docker compose up -d database
```

## Configuration

Copy environment overrides into `.env.local` on the target machine. Do not commit production secrets.

```dotenv
APP_ENV=prod
APP_SECRET=change-this
DATABASE_URL="postgresql://app:change-this@127.0.0.1:5432/agentag?serverVersion=16&charset=utf8"
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
```

`AGENTAG_WORKSPACE_PATH` is a template directory, not a run directory. AgentTag copies its contents into a per-session workspace before each thread is handled. The runtime root is the parent directory of the template path.

Recommended filesystem shape:

```text
/srv/agentag/
  app/                         # AgentTag application checkout
  workspace/                   # manually managed template
    AGENTS.md                  # generic agent instructions
    skills/                    # optional reusable skills
    .codex-plugin/             # optional Codex plugin files
    docs/                      # optional shared knowledge
  runs/session-<hash>/         # copied template for one chat thread
  artifacts/run-<id>/          # Codex last-message/artifacts
```

You can version the workspace template however you prefer. AgentTag only needs the configured path to exist; it does not hard-code or fetch a workspace repository. The `.git` directory of the template is not copied into session workspaces.

If Codex should work on company repositories, put the available SSH clone URLs and preferred clone layout directly in the workspace `AGENTS.md`. AgentTag copies the template per thread; any clone commands Codex runs happen inside that session workspace, so one thread cannot mutate another session workspace.

## Workspace Capabilities

AgentTag does not define or parse a custom tool YAML format. Give Codex capabilities by placing normal Codex-readable material in the workspace template:

- `AGENTS.md` for instructions, policy, and expected workflows.
- `skills/` for reusable skills.
- Codex plugins or MCP configuration, if you use them.
- Shared docs or examples the agent should consult.

Opening a pull request or writing a Linear comment is not sensitive by itself. Pushing to main/protected branches, force-pushing, deleting data, overwriting data, and other destructive changes require confirmation; put the exact policy in `AGENTS.md` so Codex applies it while using your workspace-provided capabilities.

## Local Development

Install dependencies:

```bash
composer install
```

Create a local workspace template:

```bash
mkdir -p var/dev-workspace
cat > var/dev-workspace/AGENTS.md <<'EOF'
Use the repository instructions. Keep Mattermost replies concise and answer in the user's language.
EOF
```

Set local overrides if needed:

```dotenv
AGENTAG_WORKSPACE_PATH=%kernel.project_dir%/var/dev-workspace
MESSENGER_TRANSPORT_DSN=in-memory://
```

Run the development server:

```bash
symfony server:start
```

Check the app:

```bash
curl http://127.0.0.1:8000/health
curl http://127.0.0.1:8000/ready
```

Run quality checks:

```bash
composer check
```

Useful console commands:

```bash
bin/console agentag:config:validate
bin/console agentag:runs:failed
bin/console agentag:memories:list
bin/console agentag:memories:delete <id>
bin/console agentag:workspace:inspect
bin/console agentag:workspace:cleanup --older-than-days=7
```

`/health` is a liveness check. `/ready` checks database connectivity and returns HTTP 503 when the database is unavailable.

## Runner Model

The webhook records an accepted run and dispatches it to Symfony Messenger. A worker consumes that message, builds a generic prompt from the session context, and runs `CodexCliRunner` inside the isolated session workspace.

The default runner invokes:

```text
codex exec
--dangerously-bypass-approvals-and-sandbox
--skip-git-repo-check
--json
--cd <session-workspace>
--output-last-message <artifacts-dir>/codex-last-message.txt
```

The generated prompt tells the agent to answer in the same language as the latest user message. Codex JSON progress messages are streamed into run events and, for Mattermost, posted back to the thread. The final Codex message is posted if it was not already posted as progress.

Run records store status, redacted input/output/log summaries, context snapshot, workspace path, artifacts, requester/source event IDs, exit code, and token counts when available. Session token totals are computed from linked runs.

## Mattermost Usage

Interaction model:

- Mention `@Codex` in a root Mattermost message to start a new session.
- Continue in the same Mattermost thread to keep context.
- Post a new `@Codex ...` message in the same thread while a run is active to interrupt it and start a replacement run with the new thread context.
- Post `@Codex stop` in the same thread to interrupt the active run without starting a replacement.
- Start a new root message/thread for a new independent topic.
- The webhook returns no canned acknowledgement for normal runs; the worker posts agent-generated progress and final messages.
- The bot shows typing while a run is queued or posting progress.
- Normal run responses are kept Mattermost-friendly and are instructed to match the user's language.

Endpoint:

```text
POST /integrations/mattermost/webhook
```

Configure `MATTERMOST_WEBHOOK_TOKEN` for outgoing webhook validation. Configure `MATTERMOST_BASE_URL` and `MATTERMOST_BOT_TOKEN` so AgentTag can fetch thread context, show typing, and post progress/final messages. Without those values, local development falls back to the inbound webhook message and skips API posts.

Global memory is explicit-only:

- `@Codex remember that <content>` stores a memory after sensitive-value redaction.
- Secret-only content is refused.
- `@Codex memories` or `@Codex what do you remember?` lists memories with IDs.
- `@Codex delete memory <id>` deletes a memory by ID.
- No memory is added automatically from model output.

Sensitive values are redacted from persisted context, runner output, run events, logs, memories, and admin fields. `AGENTAG_REDACTION_PATTERNS` can add newline-separated PCRE patterns or a JSON array of PCRE patterns.

## Slack

Slack support is intentionally thinner than Mattermost. It can be disabled with:

```dotenv
SLACK_ENABLED=0
```

The Slack events endpoint is:

```text
POST /integrations/slack/events
```

Configure `SLACK_VERIFICATION_TOKEN` if you use Slack's verification token flow. Leave it empty only for local development.

## Production Deployment

Host-based nginx + PHP-FPM deployment docs and config live in [prod/README.md](prod/README.md). That guide targets Ubuntu 24.04, runs outside Docker, assumes PostgreSQL already exists, and runs the Messenger worker as root so Codex and its child commands execute as root on the host.

## Admin Panel

`/admin` is protected by in-memory HTTP Basic credentials from `AGENTAG_ADMIN_USER` and `AGENTAG_ADMIN_PASSWORD`.

The panel is read-only and exposes sessions, runs, run events, approvals, and global memories. Create, edit, delete, and batch delete actions are disabled in the UI and rejected by controllers. Use explicit chat commands or console commands for supported mutations such as deleting a memory by ID.

## Review Discipline

Implementation should be reviewed against:

- Functional correctness.
- Security and data safety.
- Reliability and operations.
- Clear Mattermost interaction quality.
- Test coverage for the changed behavior.
