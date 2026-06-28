<?php

namespace App\AgentTag\Developer;

use App\AgentTag\Workflow\WorkflowDefinition;

final readonly class DeveloperSpecPromptBuilder
{
    private const REQUIRED_TEMPLATE_SECTIONS = [
        'context',
        'data model',
        'services',
        'apis',
        'execution flow',
        'security',
        'tests',
        'migration/deployment',
        'risks',
        'rollout',
    ];

    public function build(WorkflowDefinition $workflow, DeveloperSpecInput $input): string
    {
        $this->assertWorkflowTemplate($workflow);

        return implode("\n\n", array_filter([
            sprintf('Workflow: %s', $workflow->name()),
            sprintf('Workflow version: %s', $workflow->version() ?? '(none)'),
            "Instructions:\n".$workflow->instructions(),
            "Technical spec template:\n".$workflow->outputTemplate(),
            $this->inlineSection($input),
            $this->linearIssueSection($input),
            'Required result: Generate a technical spec and link back to the source prompt or Linear issue.',
        ]));
    }

    private function assertWorkflowTemplate(WorkflowDefinition $workflow): void
    {
        if ('' === trim($workflow->instructions()) || '' === trim($workflow->outputTemplate())) {
            throw new \InvalidArgumentException('Developer workflows must define instructions and output_template in the versioned workflow repository.');
        }

        $template = strtolower($workflow->outputTemplate());
        foreach (self::REQUIRED_TEMPLATE_SECTIONS as $section) {
            if (!str_contains($template, $section)) {
                throw new \InvalidArgumentException(sprintf('Developer workflow output_template must include "%s".', $section));
            }
        }
    }

    private function inlineSection(DeveloperSpecInput $input): ?string
    {
        return '' === trim($input->inlineFunctionalSpec()) ? null : "Functional spec input:\n".$input->inlineFunctionalSpec();
    }

    private function linearIssueSection(DeveloperSpecInput $input): ?string
    {
        return null === $input->linearIssueIdentifier() ? null : sprintf('Source Linear issue: %s', $input->linearIssueIdentifier());
    }
}
