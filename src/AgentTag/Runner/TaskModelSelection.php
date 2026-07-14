<?php

namespace App\AgentTag\Runner;

final readonly class TaskModelSelection
{
    /** @var array<string, array{agent: string, model: string, effort: string, display: string}> */
    private const array ROUTES = [
        'luna-max' => ['agent' => 'main', 'model' => 'gpt-5.6-luna', 'effort' => 'max', 'display' => 'GPT-5.6 Luna'],
        'terra-max' => ['agent' => 'terra-max', 'model' => 'gpt-5.6-terra', 'effort' => 'max', 'display' => 'GPT-5.6 Terra'],
        'sol-xhigh' => ['agent' => 'sol-xhigh', 'model' => 'gpt-5.6-sol', 'effort' => 'xhigh', 'display' => 'GPT-5.6 Sol'],
    ];

    private function __construct(
        public string $route,
        public string $agent,
        public string $model,
        public string $effort,
        public string $displayModel,
        public string $reason,
    ) {
    }

    public static function fromRoute(string $route, string $reason): ?self
    {
        $route = strtolower(trim($route));
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? $reason);
        $configuration = self::ROUTES[$route] ?? null;
        if (null === $configuration || '' === $reason) {
            return null;
        }

        return new self(
            $route,
            $configuration['agent'],
            $configuration['model'],
            $configuration['effort'],
            $configuration['display'],
            substr($reason, 0, 240),
        );
    }

    public static function mainLuna(string $reason = 'Demande courante traitée directement par l’agent principal.'): self
    {
        return self::fromRoute('luna-max', $reason) ?? throw new \LogicException('The Luna route must be valid.');
    }

    public function usesSubagent(): bool
    {
        return 'main' !== $this->agent;
    }
}
