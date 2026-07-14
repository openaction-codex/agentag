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
Choose the model that must execute the user request directly.

Apply these rules in order:
1. Choose sol-xhigh for every coding task, including technical specifications, implementation, PR reviews, bug fixes, debugging, refactors, architecture, and implementation plans.
2. Choose luna-max only for simple questions about the current implementation or product, such as how something works, the current logic of a feature, or whether a known situation can occur.
3. Choose sol-medium for every other task.

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
                    'enum' => ['luna-max', 'sol-medium', 'sol-xhigh'],
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
