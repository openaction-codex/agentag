<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Runner\TaskModelSelection;
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
            TaskModelSelection::mainLuna('The intake classifier was unavailable, so the safe primary-agent default is used.'),
        );

        $outputPath = sys_get_temp_dir().'/agentag-ack-'.bin2hex(random_bytes(8)).'.txt';
        $prompt = <<<'PROMPT'
You write the immediate acknowledgement for a software agent task.
Return exactly one JSON object with string keys "title", "acknowledgement", "route", and "selection_reason" and no markdown.
- Detect and use the language of the user request.
- title: specific, 3 to 8 words, no trailing punctuation.
- acknowledgement: at most 18 words; say the workspace is ready and name the first useful action.
- route: choose exactly one value from the routing policy below.
- selection_reason: at most 18 words, in the user's language, explaining the task type or complexity that determined the route.
- Do not claim work has happened beyond preparing the workspace.

Routing policy:
- luna-max: use the primary agent for implementation/product questions and simple coding work such as wording changes, simple refactors, known-cause bugs, straightforward specifications, implementations, or PR reviews.
- sol-xhigh: use the Sol subagent for medium or advanced coding work such as contained or cross-system features, unknown-cause bugs, difficult debugging, larger refactors, architecture, non-trivial reviews, or long plans. Also use it for non-coding work needing deeper judgment or consequential recommendations.
- terra-max: use the Terra subagent for other non-coding tasks that materially benefit from fast, broad, read-heavy research, document processing, comparison, or synthesis.
- Prefer luna-max when a request does not clearly need a subagent.

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
        $route = trim(is_string($data['route'] ?? null) ? $data['route'] : '');
        $selectionReason = trim(is_string($data['selection_reason'] ?? null) ? $data['selection_reason'] : '');
        if ('' === $title || '' === $acknowledgement) {
            return null;
        }

        $selection = TaskModelSelection::fromRoute($route, $selectionReason)
            ?? TaskModelSelection::mainLuna('The intake response had no valid specialist route, so the primary agent is used.');

        return new TaskPresentation(substr($title, 0, 160), substr($acknowledgement, 0, 300), $selection);
    }

    private function fallbackTitle(string $request): string
    {
        $request = preg_replace('/^@[A-Za-z][A-Za-z0-9_-]{1,63}[:,]?\s*/', '', trim($request)) ?? trim($request);
        $request = preg_replace('/\s+/', ' ', $request) ?? $request;

        return '' === $request ? 'Working on your request' : substr($request, 0, 80);
    }
}
