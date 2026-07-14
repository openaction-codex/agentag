<?php

namespace App\AgentTag\Runner;

final readonly class TaskModelSelection
{
    /** @var array<string, array{model: string, effort: string, display: string}> */
    private const array ROUTES = [
        'luna-max' => ['model' => 'gpt-5.6-luna', 'effort' => 'max', 'display' => 'GPT-5.6 Luna'],
        'sol-medium' => ['model' => 'gpt-5.6-sol', 'effort' => 'medium', 'display' => 'GPT-5.6 Sol'],
        'sol-xhigh' => ['model' => 'gpt-5.6-sol', 'effort' => 'xhigh', 'display' => 'GPT-5.6 Sol'],
    ];

    private function __construct(
        public string $route,
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

    public static function solMedium(string $reason = 'General task handled directly with the balanced Sol profile.'): self
    {
        return self::fromRoute('sol-medium', $reason) ?? throw new \LogicException('The Sol medium route must be valid.');
    }
}
