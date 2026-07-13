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
        $modelRouting = $this->modelRouting($run);

        return trim(<<<PROMPT
You are AgentTag running a durable task inside an isolated session workspace.

Interaction rules:
- Answer in the same language as the latest user message.
- Keep Mattermost updates concise, specific, and free of raw command output or harness internals.
- During longer work, emit occasional meaningful progress messages. The first sentence becomes the current task-card stage.
- During delegated work, never emit elapsed-time or no-change updates such as "the specialist is still working". Specialist milestone notes are mirrored into the task card automatically; add a main-agent update only when it contains new, verified information.
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

{$modelRouting}

{$continuation}
PROMPT);
    }

    private function modelRouting(AgentRun $run): string
    {
        $selection = $run->modelSelection();
        if (!$selection->usesSubagent()) {
            return <<<PROMPT
Model routing decision (already displayed in the Mattermost task card):
- Use GPT-5.6 Luna with max reasoning directly in this main agent.
- Reason: {$selection->reason}
- Do not delegate unless later user steering materially changes the task's scope or complexity.
PROMPT;
        }

        $stageInstruction = null === $run->codexThreadId()
            ? sprintf('Before substantive task work, spawn exactly the project-scoped `%s` agent without full-history inheritance, give it the core task and all relevant context, wait for it, then verify and synthesize its result.', $selection->agent)
            : sprintf('Continue using the `%s` route. Reuse its earlier result; invoke it again only when the remaining work needs fresh specialist work.', $selection->agent);

        return <<<PROMPT
Model routing decision (already displayed in the Mattermost task card):
- Use {$selection->displayModel} with {$selection->effort} reasoning through the project-scoped `{$selection->agent}` subagent.
- Reason: {$selection->reason}
- {$stageInstruction}
- In the delegation prompt, require concise milestone notes in the latest user's language after concrete discoveries, implementation milestones, and verification. Use one short line in the form "Done: ... · Doing: ... · Next: ..." and never send timer-based or no-change updates.
- While the specialist runs, wait silently between concrete milestone notes. Do not invent, infer, or repeat progress that the specialist did not report.
- The Luna main agent remains responsible for coordination, verification, and the final user response.
PROMPT;
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
