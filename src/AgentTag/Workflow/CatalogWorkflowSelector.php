<?php

namespace App\AgentTag\Workflow;

use App\AgentTag\Configuration\AgentTagSettings;

final readonly class CatalogWorkflowSelector implements WorkflowSelector
{
    public function __construct(
        private AgentTagSettings $settings,
        private WorkflowCatalog $workflowCatalog,
    ) {
    }

    #[\Override]
    public function select(string $message): WorkflowSelection
    {
        try {
            $workflows = $this->workflowCatalog->all();
        } catch (\Throwable $exception) {
            return WorkflowSelection::unselected(sprintf(
                'I could not load workflows: %s',
                $exception->getMessage(),
            ));
        }

        if ([] === $workflows) {
            return WorkflowSelection::unselected('No workflows are configured. Add workflow YAML files to the configured workflows directory.');
        }

        $request = $this->requestWithoutTag($message);
        $workflowByName = $this->workflowByNormalizedName($workflows);
        $explicitName = $this->explicitWorkflowName($request);

        if (null !== $explicitName) {
            $workflow = $workflowByName[$this->normalize($explicitName)] ?? null;

            return null === $workflow
                ? WorkflowSelection::unselected(sprintf(
                    'Unknown workflow `%s`. Available workflows: %s.',
                    $explicitName,
                    $this->availableWorkflowList($workflows),
                ))
                : WorkflowSelection::selected($workflow);
        }

        $intentWorkflow = $this->intentWorkflow($request, $workflows);
        if (null !== $intentWorkflow) {
            return WorkflowSelection::selected($intentWorkflow);
        }

        $defaultWorkflow = $this->defaultWorkflow($workflows);
        if (null !== $defaultWorkflow) {
            return WorkflowSelection::selected($defaultWorkflow);
        }

        return WorkflowSelection::unselected(sprintf(
            'I could not select a workflow. Available workflows: %s. Use `workflow:<name>` after `%s`.',
            $this->availableWorkflowList($workflows),
            $this->settings->tag(),
        ));
    }

    private function requestWithoutTag(string $message): string
    {
        $tag = preg_quote($this->settings->tag(), '/');
        $request = preg_replace('/(?<![A-Za-z0-9_@])'.$tag.'(?![A-Za-z0-9_-])/i', '', $message, 1) ?? $message;

        return ltrim(trim($request), " \t\n\r\0\x0B,:;-");
    }

    private function explicitWorkflowName(string $request): ?string
    {
        foreach ([
            '/^workflow\s*[:=]\s*(?P<name>[A-Za-z][A-Za-z0-9_-]*)/i',
            '/^workflow\s+(?P<name>[A-Za-z][A-Za-z0-9_-]*)/i',
            '/^\/(?P<name>[A-Za-z][A-Za-z0-9_-]*)(?:\s|$)/',
        ] as $pattern) {
            if (1 === preg_match($pattern, $request, $matches)) {
                return $matches['name'];
            }
        }

        return null;
    }

    /**
     * @param list<WorkflowDefinition> $workflows
     *
     * @return array<string, WorkflowDefinition>
     */
    private function workflowByNormalizedName(array $workflows): array
    {
        $workflowByName = [];
        foreach ($workflows as $workflow) {
            $workflowByName[$this->normalize($workflow->name())] = $workflow;
        }

        return $workflowByName;
    }

    /**
     * @param list<WorkflowDefinition> $workflows
     */
    private function intentWorkflow(string $request, array $workflows): ?WorkflowDefinition
    {
        $normalizedRequest = $this->normalize($request);
        $tokens = $this->tokens($request);
        $firstToken = $tokens[0] ?? '';
        $bestScore = 0;
        $bestWorkflow = null;

        foreach ($workflows as $workflow) {
            $score = 0;
            if ($firstToken === $this->normalize($workflow->name())) {
                $score += 100;
            }

            foreach ($workflow->triggers() as $trigger) {
                if (str_contains($normalizedRequest, $this->normalize($trigger))) {
                    ++$score;
                }
            }

            if ($score > $bestScore || ($score === $bestScore && null !== $bestWorkflow && $workflow->name() < $bestWorkflow->name())) {
                $bestScore = $score;
                $bestWorkflow = $workflow;
            }
        }

        return $bestScore > 0 ? $bestWorkflow : null;
    }

    /**
     * @param list<WorkflowDefinition> $workflows
     */
    private function defaultWorkflow(array $workflows): ?WorkflowDefinition
    {
        foreach ($workflows as $workflow) {
            if ($workflow->default()) {
                return $workflow;
            }
        }

        return 1 === count($workflows) ? $workflows[0] : null;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $request): array
    {
        $tokens = preg_split('/[^A-Za-z0-9_-]+/', $request, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $tokens) {
            return [];
        }

        return array_map(fn (string $token): string => $this->normalize($token), $tokens);
    }

    /**
     * @param list<WorkflowDefinition> $workflows
     */
    private function availableWorkflowList(array $workflows): string
    {
        $names = array_map(static fn (WorkflowDefinition $workflow): string => $workflow->name(), $workflows);
        sort($names);

        return implode(', ', array_map(static fn (string $name): string => '`'.$name.'`', $names));
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}
