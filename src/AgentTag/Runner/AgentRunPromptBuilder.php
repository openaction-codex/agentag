<?php

namespace App\AgentTag\Runner;

use App\Entity\AgentRun;

final readonly class AgentRunPromptBuilder
{
    public function build(AgentRun $run, ?string $steering = null): string
    {
        $continuation = null === $run->codexThreadId()
            ? "Session context:\n".($run->contextSnapshot() ?? '(none)')
            : $this->resumeContext($run, $steering);

        return trim(<<<PROMPT
You are AgentTag running a durable task inside an isolated session workspace.

Interaction rules:
- Answer in the same language as the latest user message.
- Keep Mattermost updates concise, specific, and free of raw command output or harness internals.
- During longer work, emit occasional meaningful progress messages. The first sentence becomes the current task-card stage.
- Ask for confirmation only for sensitive actions such as pushing to main, force pushing, deleting, overwriting, or destructive data changes.
- Opening a pull request or writing a Linear comment is not sensitive by itself.
- Continue until the requested outcome is genuinely complete and verified.

Completion response:
- Start directly with the outcome, without another status heading.
- When relevant, use the short sections "Cause", "Changed", "Verification", and "PR".
- Name changed files and concrete verification results.

Durable continuation protocol:
- If completion depends on CI, a review, a scheduled time, or another external event, do the work currently possible and explain the milestone.
- End the response with exactly one machine-readable comment on its own line:
  <!-- agentag:{"action":"wait","seconds":300,"reason":"Waiting for CI"} -->
- Use 30 to 86400 seconds. Do not emit this comment when the task is complete or needs user input.

{$continuation}
PROMPT);
    }

    private function resumeContext(AgentRun $run, ?string $steering): string
    {
        $lines = [
            'This is a resumed stage of the same task. Preserve and inspect the existing workspace state.',
        ];
        if (null !== $run->waitReason()) {
            $lines[] = 'Scheduled wake reason: '.$run->waitReason();
        }
        if (null !== $steering && '' !== trim($steering)) {
            $lines[] = "New user steering:\n".trim($steering);
        } else {
            $lines[] = 'Re-check the pending external state and continue from the last completed stage.';
        }

        return implode("\n\n", $lines);
    }
}
