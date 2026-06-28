<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'agent_run')]
class AgentRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param list<string> $artifacts
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: ChatSession::class, inversedBy: 'runs')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ChatSession $session,
        #[ORM\Column(length: 32)]
        private string $status,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $inputSummary = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $outputSummary = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $contextSnapshot = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $workflowName = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $workflowVersion = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $workflowRevision = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $workspacePath = null,
        /**
         * @var list<string>
         */
        #[ORM\Column(type: 'json')]
        private array $artifacts = [],
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $logSummary = null,
        #[ORM\Column(nullable: true)]
        private ?int $exitCode = null,
        #[ORM\Column(nullable: true)]
        private ?int $inputTokens = null,
        #[ORM\Column(nullable: true)]
        private ?int $outputTokens = null,
        #[ORM\Column(nullable: true)]
        private ?int $totalTokens = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function session(): ChatSession
    {
        return $this->session;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function inputSummary(): ?string
    {
        return $this->inputSummary;
    }

    public function contextSnapshot(): ?string
    {
        return $this->contextSnapshot;
    }

    public function outputSummary(): ?string
    {
        return $this->outputSummary;
    }

    public function workflowName(): ?string
    {
        return $this->workflowName;
    }

    public function workflowVersion(): ?string
    {
        return $this->workflowVersion;
    }

    public function workflowRevision(): ?string
    {
        return $this->workflowRevision;
    }

    public function workspacePath(): ?string
    {
        return $this->workspacePath;
    }

    /**
     * @return list<string>
     */
    public function artifacts(): array
    {
        return $this->artifacts;
    }

    public function logSummary(): ?string
    {
        return $this->logSummary;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function inputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function outputTokens(): ?int
    {
        return $this->outputTokens;
    }

    public function totalTokens(): ?int
    {
        return $this->totalTokens;
    }

    /**
     * @param list<string> $artifacts
     */
    public function recordRunnerResult(
        string $status,
        string $outputSummary,
        string $logSummary,
        string $workspacePath,
        array $artifacts,
        int $exitCode,
        ?\App\AgentTag\Runner\TokenUsage $tokenUsage,
    ): void {
        $this->status = $status;
        $this->outputSummary = $outputSummary;
        $this->logSummary = $logSummary;
        $this->workspacePath = $workspacePath;
        $this->artifacts = $artifacts;
        $this->exitCode = $exitCode;
        $this->inputTokens = $tokenUsage?->inputTokens();
        $this->outputTokens = $tokenUsage?->outputTokens();
        $this->totalTokens = $tokenUsage?->totalTokens();
    }
}
