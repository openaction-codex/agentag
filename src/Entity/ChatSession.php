<?php

namespace App\Entity;

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

    /**
     * @var Collection<int, AgentRun>
     */
    #[ORM\OneToMany(targetEntity: AgentRun::class, mappedBy: 'session')]
    private Collection $runs;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $sessionKey,
        #[ORM\Column(length: 32)]
        private string $platform,
        #[ORM\Column(length: 255)]
        private string $teamId,
        #[ORM\Column(length: 255)]
        private string $channelId,
        #[ORM\Column(length: 255)]
        private string $threadId,
        #[ORM\Column]
        private \DateTimeImmutable $lastActivityAt,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $summary = null,
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

    public function platform(): string
    {
        return $this->platform;
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

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function lastActivityAt(): \DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    /**
     * @return Collection<int, AgentRun>
     */
    public function runs(): Collection
    {
        return $this->runs;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->lastActivityAt = $now;
    }
}
