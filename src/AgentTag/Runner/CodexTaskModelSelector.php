<?php

namespace App\AgentTag\Runner;

use App\AgentTag\Configuration\AgentTagSettings;
use Psr\Log\LoggerInterface;

final readonly class CodexTaskModelSelector implements TaskModelSelector
{
    public function __construct(
        private ProcessFactory $processFactory,
        private AgentTagSettings $settings,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function select(string $request): TaskModelSelection
    {
        $fallback = TaskModelSelection::solMedium('The model selector was unavailable, so the general-purpose route was used.');
        $identifier = bin2hex(random_bytes(8));
        $outputPath = sys_get_temp_dir().'/agentag-model-selection-'.$identifier.'.json';
        $schemaPath = sys_get_temp_dir().'/agentag-model-selection-schema-'.$identifier.'.json';
        $prompt = <<<'PROMPT'
You are a model router. Minimize quota usage while preserving correctness, judgment, and completeness. Route by actual scope, uncertainty, sensitivity, and verifiability, not merely by keywords such as "code" or "implement."

Honor an explicit request for a model or route. When only a model is requested, choose the appropriate effort for that model from the available routes.

Routing:
- Stop/cancel, ping, health/model/skills check, or simple confirmation: luna-low.
- Linear listing/status/assignment/labels/comments or simple writing: luna-medium.
- Narrow product question or isolated UI smoke test: luna-max.
- Codebase investigation or routine production diagnosis: terra-high.
- Technical specification (`$specify-issue`): terra-xhigh; sol-medium for security, architecture, major migrations, unresolved product decisions, or large scope.
- Functional PR validation (`$validate-pr`): terra-high; luna-max for isolated UI smoke tests; sol-medium for security, concurrency, data integrity, multiple systems, or large scope.
- Routine PR review: terra-xhigh; sol-medium for security, architecture, major migrations, concurrency, performance, unresolved decisions, or large scope.
- Clear bug fix or small feature with a precise issue/spec: terra-high; sol-medium for security, data integrity, or large scope.
- Objectively verifiable coding without important unknowns, including CI repair, explicit review feedback, fixtures, tests, mechanical refactoring, known validation rules, or small UI fixes: terra-high; luna-max if extremely small and isolated.
- Rebase, backport, or fork sync: terra-max; sol-medium if conflicts require substantial semantic or architectural decisions.
- Sales/account research: terra-medium; sol-medium for unusually deep strategic synthesis.
- Routine, reversible system operations: terra-high; terra-xhigh for production writes; sol-medium for security incidents, destructive operations, or broad unknown-root-cause failures.
- Other coding: terra-xhigh when bounded or strongly verifiable; sol-medium for architecture, multi-tenancy, complex UI/accessibility, concurrency, major performance/indexing work, migrations, sensitive logic, broad unknown-root-cause debugging, or large implementations. Use sol-xhigh only when exceptional complexity, scope, uncertainty, and consequences occur together.

Rules:
- Tests, review, CI, and PR creation are normal workflow steps and do not alone justify Sol.
- Strong verification justifies Terra only when it covers the risky behavior.
- If uncertain, use terra-xhigh for ordinary coding and sol-medium for sensitive or genuinely unknown work.
- A cheaper model must request Sol escalation before risky changes if it discovers materially greater scope, sensitivity, or uncertainty.

Return only the JSON object required by the output schema. Keep selection_reason concise and in the same language as the request when it is French or English.

User request:
PROMPT;
        $prompt .= "\n".$request;

        try {
            $schema = json_encode($this->outputSchema(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
            if (false === file_put_contents($schemaPath, $schema)) {
                throw new \RuntimeException('Unable to write the model-selection output schema.');
            }

            $process = $this->processFactory->create([
                'codex', 'exec',
                '--ephemeral',
                '--ignore-rules',
                '--skip-git-repo-check',
                '--sandbox', 'read-only',
                '--model', $this->settings->modelSelectionModel(),
                '-c', 'model_reasoning_effort="medium"',
                '--output-schema', $schemaPath,
                '--output-last-message', $outputPath,
                '-',
            ], sys_get_temp_dir(), [], $prompt, $this->settings->modelSelectionTimeoutSeconds());
            $callback = static function (string $_type, string $_buffer): void {};
            $process->start($callback);
            $process->wait($callback);
            if (0 !== $process->exitCode() || !is_file($outputPath)) {
                return $fallback;
            }

            return $this->parse((string) file_get_contents($outputPath)) ?? $fallback;
        } catch (\Throwable $exception) {
            $this->logger?->warning('Task model selection failed; using the general-purpose route.', [
                'error' => $exception->getMessage(),
            ]);

            return $fallback;
        } finally {
            foreach ([$outputPath, $schemaPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'route' => [
                    'type' => 'string',
                    'enum' => [
                        'luna-low',
                        'luna-medium',
                        'luna-max',
                        'terra-medium',
                        'terra-high',
                        'terra-xhigh',
                        'terra-max',
                        'sol-medium',
                        'sol-xhigh',
                    ],
                ],
                'selection_reason' => ['type' => 'string'],
            ],
            'required' => ['route', 'selection_reason'],
            'additionalProperties' => false,
        ];
    }

    private function parse(string $output): ?TaskModelSelection
    {
        $data = json_decode(trim($output), true);
        if (!is_array($data)) {
            return null;
        }

        $route = is_string($data['route'] ?? null) ? $data['route'] : '';
        $reason = is_string($data['selection_reason'] ?? null) ? $data['selection_reason'] : '';

        return TaskModelSelection::fromRoute($route, $reason);
    }
}
