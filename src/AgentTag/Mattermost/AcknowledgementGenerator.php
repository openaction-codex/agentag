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
            'Traitement de votre demande',
            'Espace prêt. J’analyse la demande et détermine la première action utile.',
            TaskModelSelection::mainLuna('Le classifieur initial est indisponible ; la route sûre de l’agent principal est utilisée.'),
        );

        $outputPath = sys_get_temp_dir().'/agentag-ack-'.bin2hex(random_bytes(8)).'.txt';
        $prompt = <<<'PROMPT'
You write the immediate acknowledgement for a software agent task.
Return exactly one JSON object with string keys "title", "acknowledgement", "route", and "selection_reason" and no markdown.
- Write the title, acknowledgement, and selection_reason only in French or English.
- Use English only when the user request is confidently determined to be English. Otherwise use French, including when the request is French, mixed, ambiguous, language-neutral, written in another language, or its language is uncertain.
- title: specific, 3 to 8 words, no trailing punctuation.
- acknowledgement: at most 18 words; say the workspace is ready and name the first useful action.
- route: choose exactly one value from the routing policy below.
- selection_reason: at most 18 words, in the selected French-or-English language, explaining the task type or complexity that determined the route.
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
                '-c', 'model_reasoning_effort="max"',
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
            $this->logger?->warning('Acknowledgement routing model failed; using the deterministic fallback.', [
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
            ?? TaskModelSelection::mainLuna('La réponse initiale ne contient aucune route spécialiste valide ; l’agent principal est utilisé.');

        return new TaskPresentation(substr($title, 0, 160), substr($acknowledgement, 0, 300), $selection);
    }
}
