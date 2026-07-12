<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'run_event')]
class RunEvent
{
    public const TYPE_PROGRESS_UPDATE = 'progress_update';
    public const TYPE_WORKSPACE_PREPARED = 'workspace_prepared';
    public const TYPE_RUNNER_STARTED = 'runner_started';
    public const TYPE_RUNNER_FINISHED = 'runner_finished';
    public const TYPE_TOKEN_USAGE = 'token_usage';
    public const TYPE_CANCELLATION_REQUESTED = 'cancellation_requested';
    public const TYPE_STEERING_RECEIVED = 'steering_received';
    public const TYPE_RETRY_REQUESTED = 'retry_requested';
    public const TYPE_TASK_WAITING = 'task_waiting';
    public const TYPE_TASK_RESUMED = 'task_resumed';
    public const TYPE_WORKSPACE_DISCARDED = 'workspace_discarded';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: AgentRun::class, inversedBy: 'events')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private AgentRun $run,
        #[ORM\Column(length: 64)]
        private string $type,
        #[ORM\Column(type: 'text')]
        private string $message,
        #[ORM\Column(type: 'json')]
        private array $metadata,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function run(): AgentRun
    {
        return $this->run;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
