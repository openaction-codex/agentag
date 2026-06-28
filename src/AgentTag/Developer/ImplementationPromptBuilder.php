<?php

namespace App\AgentTag\Developer;

use App\AgentTag\Workflow\WorkflowDefinition;

final readonly class ImplementationPromptBuilder
{
    public function build(WorkflowDefinition $workflow, ImplementationRunInput $input): string
    {
        if (!in_array($input->repositoryClone()->repository()->identifier(), $workflow->repositories(), true) && !in_array('*', $workflow->repositories(), true)) {
            throw new \InvalidArgumentException(sprintf('Workflow `%s` is not allowed to implement repository `%s`.', $workflow->name(), $input->repositoryClone()->repository()->identifier()));
        }

        return implode("\n\n", array_filter([
            sprintf('Workflow: %s', $workflow->name()),
            sprintf('Runner mode: %s', $workflow->runnerMode()),
            "Technical spec:\n".$input->technicalSpec(),
            sprintf('Repository `%s` is cloned at: %s', $input->repositoryClone()->repository()->identifier(), $input->repositoryClone()->path()),
            sprintf('Use or create branch: %s', $input->branchName()),
            $this->sessionSection($input),
            $this->checksSection($input),
            "Required summary:\nChanged files, test results, artifacts, token usage if available, remaining risks, and next review steps.",
        ]));
    }

    private function sessionSection(ImplementationRunInput $input): ?string
    {
        $sessionContext = $input->sessionContext();
        if (null === $sessionContext) {
            return null;
        }

        $lines = ['Relevant session context:'];
        foreach ($sessionContext->messages() as $message) {
            $lines[] = sprintf('- %s (%s): %s', $message->authorId(), $message->externalId(), $this->singleLine($message->text()));
        }

        return implode("\n", $lines);
    }

    private function checksSection(ImplementationRunInput $input): string
    {
        if ([] === $input->checkCommands()) {
            return 'Check commands: none configured.';
        }

        return "Check commands:\n".implode("\n", array_map(static fn (string $command): string => '- '.$command, $input->checkCommands()));
    }

    private function singleLine(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    }
}
