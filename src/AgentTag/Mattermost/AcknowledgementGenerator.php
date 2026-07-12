<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Runner\ProcessFactory;
use Psr\Log\LoggerInterface;

final class AcknowledgementGenerator implements TaskPresentationGenerator
{
    public function __construct(
        private readonly ProcessFactory $processFactory,
        private readonly AgentTagSettings $settings,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function generate(string $request, string $workingDirectory): TaskPresentation
    {
        $fallback = new TaskPresentation(
            $this->fallbackTitle($request),
            'Workspace ready. I’m inspecting the request and deciding the first useful step.',
        );

        $outputPath = sys_get_temp_dir().'/agentag-ack-'.bin2hex(random_bytes(8)).'.txt';
        $prompt = <<<'PROMPT'
You write the immediate acknowledgement for a software agent task.
Return exactly one JSON object with string keys "title" and "acknowledgement" and no markdown.
- Detect and use the language of the user request.
- title: specific, 3 to 8 words, no trailing punctuation.
- acknowledgement: at most 18 words; say the workspace is ready and name the first useful action.
- Do not claim work has happened beyond preparing the workspace.

User request:
PROMPT;
        $prompt .= "\n".$request;

        try {
            $process = $this->processFactory->create([
                'codex', 'exec',
                '--ephemeral',
                '--ignore-rules',
                '--skip-git-repo-check',
                '--sandbox', 'read-only',
                '--model', $this->settings->acknowledgementModel(),
                '-c', 'model_reasoning_effort="low"',
                '--output-last-message', $outputPath,
                '-',
            ], $workingDirectory, [], $prompt, $this->settings->acknowledgementTimeoutSeconds());
            $callback = static function (string $_type, string $_buffer): void {};
            $process->start($callback);
            $process->wait($callback);
            if (0 !== $process->exitCode() || !is_file($outputPath)) {
                return $fallback;
            }

            $presentation = $this->parse((string) file_get_contents($outputPath));

            return $presentation ?? $fallback;
        } catch (\Throwable $exception) {
            $this->logger?->warning('Cheap acknowledgement model failed; using the deterministic fallback.', [
                'error' => $exception->getMessage(),
            ]);

            return $fallback;
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    private function parse(string $output): ?TaskPresentation
    {
        $output = trim($output);
        $output = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $output) ?? $output;
        $data = json_decode($output, true);
        if (!is_array($data)) {
            return null;
        }

        $title = trim(is_string($data['title'] ?? null) ? $data['title'] : '');
        $acknowledgement = trim(is_string($data['acknowledgement'] ?? null) ? $data['acknowledgement'] : '');
        if ('' === $title || '' === $acknowledgement) {
            return null;
        }

        return new TaskPresentation(substr($title, 0, 160), substr($acknowledgement, 0, 300));
    }

    private function fallbackTitle(string $request): string
    {
        $request = preg_replace('/^@[A-Za-z][A-Za-z0-9_-]{1,63}[:,]?\s*/', '', trim($request)) ?? trim($request);
        $request = preg_replace('/\s+/', ' ', $request) ?? $request;

        return '' === $request ? 'Working on your request' : substr($request, 0, 80);
    }
}
