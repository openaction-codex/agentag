<?php

namespace App\AgentTag\Runner;

use App\AgentTag\Codebase\CodebaseContext;
use App\Entity\AgentRun;

final readonly class AgentRunPromptBuilder
{
    public function build(AgentRun $run, CodebaseContext $codebaseContext): string
    {
        return trim(sprintf(
            <<<'PROMPT'
You are AgentTag running inside an isolated session workspace.

Interaction rules:
- Answer in the same language as the latest user message in the thread.
- Write Mattermost-friendly responses: concise, clear, and useful without long preambles.
- During longer work, emit brief progress updates that reflect what you are actually doing.
- Ask for confirmation only for sensitive actions such as pushing to main, force pushing, deleting, overwriting, or destructive data changes.
- Opening a pull request or writing a Linear comment is not sensitive by itself.

Session context:
%s

%s
PROMPT,
            $run->contextSnapshot() ?? '(none)',
            $codebaseContext->promptSection(),
        ));
    }
}
