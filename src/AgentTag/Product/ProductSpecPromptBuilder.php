<?php

namespace App\AgentTag\Product;

use App\AgentTag\Workflow\WorkflowDefinition;

final readonly class ProductSpecPromptBuilder
{
    public function build(WorkflowDefinition $workflow, ProductSpecInput $input): string
    {
        if ('' === trim($workflow->instructions()) || '' === trim($workflow->outputTemplate())) {
            throw new \InvalidArgumentException('Product workflows must define instructions and output_template in the versioned workflow repository.');
        }

        return implode("\n\n", array_filter([
            sprintf('Workflow: %s', $workflow->name()),
            sprintf('Workflow version: %s', $workflow->version() ?? '(none)'),
            "Instructions:\n".$workflow->instructions(),
            "Output template:\n".$workflow->outputTemplate(),
            $this->inlineSection($input),
            $this->threadSection($input),
            $this->codebaseSection($input),
            $this->linearIssueSection($input),
            "Required result:\nDraft a functional spec and user-story breakdown. Include an Open questions section for human review.",
        ]));
    }

    private function inlineSection(ProductSpecInput $input): ?string
    {
        return '' === trim($input->inlineText()) ? null : "Inline request:\n".$input->inlineText();
    }

    private function threadSection(ProductSpecInput $input): ?string
    {
        $threadContext = $input->threadContext();
        if (null === $threadContext) {
            return null;
        }

        $lines = ['Thread context:'];
        foreach ($threadContext->messages() as $message) {
            $lines[] = sprintf('- %s (%s): %s', $message->authorId(), $message->externalId(), $this->singleLine($message->text()));
        }

        return implode("\n", $lines);
    }

    private function codebaseSection(ProductSpecInput $input): ?string
    {
        return null === $input->codebaseContext() ? null : $input->codebaseContext()->promptSection();
    }

    private function linearIssueSection(ProductSpecInput $input): ?string
    {
        return null === $input->linearIssueIdentifier() ? null : sprintf('Linear issue input: %s', $input->linearIssueIdentifier());
    }

    private function singleLine(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    }
}
