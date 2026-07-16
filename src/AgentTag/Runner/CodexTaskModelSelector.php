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
1. Choose luna-low only for an extremely simple request whose entire purpose is to stop, cancel, or interrupt the agent's current work, or to check the current agent status or current MCP server status.
2. Choose sol-medium for functional testing of a PR (PR functional validation) and for writing a technical specification.
3. Choose sol-xhigh for every remaining coding task, including implementation, code reviews, bug fixes, debugging, refactors, architecture, and implementation plans.
4. Choose luna-max for every remaining simple, routine task that does not require long context. This includes product questions, implementation questions, general questions, classification, extraction, short summarization, status updates, and other simple MCP reads.
5. Choose sol-medium for every remaining non-coding task, especially work requiring long context, broad synthesis, deeper judgment, or consequential recommendations.

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
                    'enum' => ['luna-low', 'luna-max', 'sol-medium', 'sol-xhigh'],
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
