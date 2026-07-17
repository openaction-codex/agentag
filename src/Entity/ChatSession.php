<?php

namespace App\Entity;

use App\AgentTag\Runner\TaskModelSelection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_session')]
#[ORM\UniqueConstraint(name: 'uniq_chat_session_key', columns: ['session_key'])]
class ChatSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, AgentRun> */
    #[ORM\OneToMany(targetEntity: AgentRun::class, mappedBy: 'session')]
    private Collection $runs;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $modelRoute = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $modelSelectionReason = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $sessionKey,
        #[ORM\Column(length: 255)]
        private string $teamId,
        #[ORM\Column(length: 255)]
        private string $channelId,
        #[ORM\Column(length: 255)]
        private string $threadId,
        #[ORM\Column]
        private \DateTimeImmutable $lastActivityAt,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $workspacePath = null,
    ) {
        $this->runs = new ArrayCollection();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sessionKey(): string
    {
        return $this->sessionKey;
    }

    public function teamId(): string
    {
        return $this->teamId;
    }

    public function channelId(): string
    {
        return $this->channelId;
    }

    public function threadId(): string
    {
        return $this->threadId;
    }

    public function lastActivityAt(): \DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function workspacePath(): ?string
    {
        return $this->workspacePath;
    }

    public function modelSelection(): ?TaskModelSelection
    {
        return TaskModelSelection::fromRoute($this->modelRoute ?? '', $this->modelSelectionReason ?? '');
    }

    /** @return Collection<int, AgentRun> */
    public function runs(): Collection
    {
        return $this->runs;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->lastActivityAt = $now;
    }

    public function assignWorkspacePath(string $workspacePath): void
    {
        $this->workspacePath = $workspacePath;
    }

    public function selectModel(TaskModelSelection $modelSelection): void
    {
        if (null !== $this->modelSelection()) {
            return;
        }

        $this->modelRoute = $modelSelection->route;
        $this->modelSelectionReason = $modelSelection->reason;
    }

    public function inputTokens(): int
    {
        return $this->sumRunTokens(static fn (AgentRun $run): ?int => $run->inputTokens());
    }

    public function outputTokens(): int
    {
        return $this->sumRunTokens(static fn (AgentRun $run): ?int => $run->outputTokens());
    }

    public function totalTokens(): int
    {
        return $this->sumRunTokens(static fn (AgentRun $run): ?int => $run->totalTokens());
    }

    /** @param callable(AgentRun): ?int $tokenAccessor */
    private function sumRunTokens(callable $tokenAccessor): int
    {
        $total = 0;
        foreach ($this->runs as $run) {
            $total += $tokenAccessor($run) ?? 0;
        }

        return $total;
    }
}
