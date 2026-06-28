# AgentTag

AgentTag is a self-hosted Symfony bot for Mattermost first, Slack later. It responds to one configured instance tag, defaulting to `@Codex`, and runs versioned workflows with Codex CLI, local tools, MCP tools, and codebase access.

## Current Implementation Status

This repository is being implemented user story by user story from Linear `OPE-1100`. The current foundation provides:

- Symfony on PHP 8.4.
- PostgreSQL configuration through `DATABASE_URL`.
- Optional Redis-compatible Messenger/cache wiring through Symfony configuration when needed later.
- A configurable AgentTag workspace layout.
- A `/health` endpoint.
- Mattermost and Slack webhook entrypoints with thread/session mapping.
- PostgreSQL-backed chat sessions and agent run records with redacted context snapshots.
- Explicit-only global memories with chat commands to remember, list, and delete entries by ID.
- Configured Linear tool instructions with persisted write audits for comments, issues, subissues, and description updates.
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
AGENTAG_CONTEXT_MAX_CHARS=12000
AGENTAG_ADMIN_USER=admin
AGENTAG_ADMIN_PASSWORD=change-this

MATTERMOST_WEBHOOK_TOKEN=change-this
MATTERMOST_BASE_URL=https://mattermost.example.com
MATTERMOST_BOT_TOKEN=change-this
MATTERMOST_RECENT_REPLY_LIMIT=20
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

AgentTag derives stable repository identifiers from SSH URL paths. For example, `git@github.com:openaction-codex/agentag.git` becomes `openaction-codex-agentag`. Workflows can request repositories by identifier or `*`:

```yaml
name: developer
repositories:
    - openaction-codex-agentag
```

When a workflow requests repository context, AgentTag clones each permitted repository with the local `git` CLI into `AGENTAG_WORKSPACE_PATH/runs/<run-id>/codebase/<repository-id>`. Those per-run clones are the only active working copies. Optional mirrors under `AGENTAG_WORKSPACE_PATH/cache/repositories/<repository-id>.git` may be used through `git clone --reference-if-able`, but cache mirrors are never used as the agent's working copy. The generated Codex prompt section tells the runner to inspect clones read-only and cite relevant file paths when answering.

Workflow files are loaded from `AGENTAG_WORKFLOWS_PATH` as local `.yaml` or `.yml` files. The workflows directory is expected to be a manually cloned, versioned repository managed outside AgentTag. Example:

```yaml
name: developer
version: v1
description: Work on implementation tasks.
default: true
triggers:
    - implement
    - fix
tools:
    - codex
    - git
repositories:
    - openaction-codex-agentag
instructions: |
    Follow the repository review discipline.
output_template: |
    Summary, verification, next steps.
runner_mode: codex-full-access
timeout_seconds: 1800
sensitivity_policy: confirm-sensitive
```

After the global tag, users can select workflows explicitly with `workflow:developer` or `/developer`. If no explicit workflow is given, AgentTag selects by workflow name/triggers, then by `default: true`, then by a single configured workflow. Unknown explicit workflows return a concise message with available options.

Product workflows should keep their functional-spec instructions and output template in the workflow YAML, for example:

```yaml
name: product
version: v1
triggers:
    - spec
instructions: |
    Draft a functional spec and user-story breakdown from the provided context.
output_template: |
    ## Problem
    ## Goals
    ## User stories
    ## Acceptance criteria
    ## Open questions
tools:
    - linear
```

AgentTag packages inline text, thread context, optional cloned codebase context, and optional Linear issue identifiers into the runner prompt. Creating Linear comments, issues, and subissues is treated as non-sensitive by default. Appending to an issue description is non-sensitive; replacing existing issue content requires confirmation.

Developer workflows use the same external-template model. Their `output_template` must include these sections so implementation plans stay consistent without hard-coding the template in AgentTag:

```yaml
name: developer
version: v1
triggers:
    - implement
instructions: |
    Generate an implementation-grade technical spec from the source functional spec.
output_template: |
    ## Context
    ## Data model
    ## Services
    ## APIs
    ## Execution flow
    ## Security
    ## Tests
    ## Migration/deployment
    ## Risks
    ## Rollout
```

Implementation runs use the configured repository context and Codex runner. The prompt sent to Codex includes the technical spec, relevant session context, the isolated repository clone path, the branch to use/create, and configured check commands. Summaries are expected to cover changed files, test results, artifacts, token usage when available, remaining risks, and next review steps.

Opening a new pull request is non-sensitive by default. Pushing a normal work branch is also non-sensitive unless your workflow policy says otherwise. Pushing to main/protected branches, force-pushing, deleting data, overwriting data, and deployments require confirmation.

Approval requests are created only for sensitive or destructive/overwrite actions. The prompt shown in chat includes the action, target system, workflow, requester, and expected effect. Approval decisions are stored with status (`approved`, `canceled`, `expired`, or `unauthorized`), approver identity when available, and timestamp. In v1 there is no Mattermost role model, so any non-empty chat user identity can approve; blank or unavailable identities are treated as unauthorized.

Tool definitions live under `AGENTAG_WORKFLOWS_PATH/tools/*.yaml` so the operator can version them with workflows:

```yaml
name: git
type: cli
command: git
arguments:
    - status
allowed_workflows:
    - developer
working_directory: codebase
environment:
    - GIT_SSH_COMMAND
timeout_seconds: 120
sensitivity: non_sensitive
confirmation_policy: default
sandbox: no_sandbox
```

`type` is `cli` or `mcp`. CLI tools define `command`; MCP tools define `server`. `allowed_workflows` limits where a tool is available, and selected runs only include tools both requested by the workflow and allowed by the tool. `sensitivity` is `non_sensitive`, `sensitive`, or `destructive`; non-sensitive tools do not require confirmation by default, while sensitive and destructive tools do. Use `confirmation_policy: always` to force confirmation for any tool. `sandbox: no_sandbox` records that the tool is permitted to run with full host access.

Linear access is provided through a configured tool named `linear`; AgentTag prepares instructions for that configured MCP or CLI tool rather than embedding a large native Linear client in v1:

```yaml
name: linear
type: mcp
server: linear
allowed_workflows:
    - product
    - developer
sensitivity: non_sensitive
confirmation_policy: default
```

Workflows that list `linear` can request issue reads and non-destructive writes. Creating comments, issues, and subissues is non-sensitive by default. Appending to descriptions is non-sensitive, while replacing existing descriptions creates an approval request before execution. Linear write results are audited with the source chat message id, workflow, requester, target issue when available, resulting issue identifiers, status, timestamp, and redacted failure summary.

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
bin/console agentag:runs:failed
bin/console agentag:memories:list
bin/console agentag:memories:delete <id>
bin/console agentag:workspace:inspect
bin/console agentag:workspace:cleanup --older-than-days=7
```

## Runner Model

AgentTag runs agent work through `AgentRunnerInterface`. The default implementation is `CodexCliRunner`, which invokes the local `codex exec` binary with:

```text
--dangerously-bypass-approvals-and-sandbox
--skip-git-repo-check
--cd <isolated-run-workspace>
--output-last-message <artifacts-dir>/codex-last-message.txt
```

The orchestrator creates the isolated run workspace under `AGENTAG_WORKSPACE_PATH/runs/<run-id>` and artifacts under `AGENTAG_WORKSPACE_PATH/artifacts/<run-id>`. Runner output, redacted logs, exit code, workspace path, artifacts, and token usage when exposed by the runner are stored on the `agent_run` record. If token usage is unavailable, AgentTag leaves token fields empty rather than guessing.

Operator inspection data is stored in PostgreSQL. Runs keep source chat event and requester IDs, workflow metadata, token usage, workspace path, artifacts, repository clone paths when available, and sanitized summaries. Progress updates and runner lifecycle events are stored as run events. Confirmation requests can be linked to the run that created them. Session token totals are computed from their runs for stats. Workspace cleanup only removes old isolated workspace/artifact directories when `--force` is used; it never deletes run or session history in v1.

## VPS Setup

A practical VPS deployment is:

1. Install PHP 8.4, Composer, PostgreSQL, Git, and the Symfony CLI or your preferred PHP process manager.
2. Clone this repository to a release directory such as `/srv/agentag/app`.
3. Create `/srv/agentag/workspace` and clone your workflows repository to `/srv/agentag/workspace/workflows`.
4. Configure `.env.local` or real environment variables with `APP_SECRET`, `DATABASE_URL`, `AGENTAG_*`, Mattermost credentials, and later Linear/GitHub tokens as needed.
5. Run `composer install --no-dev --optimize-autoloader`.
6. Run Doctrine migrations: `bin/console doctrine:migrations:migrate --no-interaction`.
7. Run the web process behind nginx/Caddy/Apache or Symfony CLI.
8. Run Symfony Messenger workers if asynchronous jobs are configured in later stories.
9. Protect logs and `.env.local` with normal server file permissions.

The read-only EasyAdmin panel will be added in a later story and protected by in-memory HTTP Basic credentials from `AGENTAG_ADMIN_USER` and `AGENTAG_ADMIN_PASSWORD`.

## Mattermost Usage Model

The interaction model is:

- Mention `@Codex` in a root Mattermost message to start a new AgentTag session.
- Continue in the same Mattermost thread to keep the same session context.
- Start a new root message/thread for a new topic.
- Each substantial agent action inside the session creates its own isolated run workspace.
- Each accepted mention creates an `agent_run` row linked to the thread `chat_session`.
- The selected workflow name, workflow version, and workflow git revision are stored on the run when available.
- The stored context snapshot includes bounded thread messages, prior run summaries, explicit-memory placeholders, and link/artifact placeholders.
- Context snapshots and input summaries are redacted before storage.

The initial webhook endpoint is:

```text
POST /integrations/mattermost/webhook
```

Configure `MATTERMOST_WEBHOOK_TOKEN` when using a Mattermost outgoing webhook token. Leave it empty only for local development.

Configure `MATTERMOST_BASE_URL` and `MATTERMOST_BOT_TOKEN` to let AgentTag fetch the root post and recent replies through Mattermost's REST API. Without those values, local development falls back to the inbound webhook message only. `MATTERMOST_RECENT_REPLY_LIMIT` bounds fetched thread messages, and `AGENTAG_CONTEXT_MAX_CHARS` bounds persisted context snapshots.

Slack support is intentionally thin until the Mattermost path is complete. It can be disabled with `SLACK_ENABLED=0`. The initial Slack events endpoint is:

```text
POST /integrations/slack/events
```

Configure `SLACK_VERIFICATION_TOKEN` if you use Slack's verification token flow. Leave it empty only for local development.

Global memory is explicit-only:

- The agent must ask before adding memory unless the user explicitly asks it to remember something.
- `@Codex remember that <content>` stores an explicit memory after redacting sensitive values. Secret-only content is refused.
- `@Codex memories` or `@Codex what do you remember?` lists current memories with stable IDs.
- `@Codex delete memory <id>` deletes a global memory by ID. Run and session history is not deleted in v1.
- Sensitive values are redacted or refused before storage.
- Stored global memories are added to later run context separately from thread summaries and prior run metadata.

## Review Discipline

Implementation should stay small and be reviewed regularly against:

- Functional correctness.
- Security and data safety.
- Performance and scalability.
- Reliability and operations.
- Readability and maintainability.
- Testing and validation.
