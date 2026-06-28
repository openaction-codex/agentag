<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'global_memory')]
class GlobalMemory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'text')]
        private string $content,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(length: 120)]
        private string $createdBy,
        #[ORM\Column(length: 32)]
        private string $sourcePlatform,
        #[ORM\Column(length: 160)]
        private string $sourceThreadId,
        #[ORM\Column(length: 160)]
        private string $sourceMessageId,
    ) {
        if ('' === trim($content)) {
            throw new \InvalidArgumentException('Global memory content must not be blank.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }

    public function sourcePlatform(): string
    {
        return $this->sourcePlatform;
    }

    public function sourceThreadId(): string
    {
        return $this->sourceThreadId;
    }

    public function sourceMessageId(): string
    {
        return $this->sourceMessageId;
    }

    public function getId(): ?int
    {
        return $this->id();
    }

    public function getContent(): string
    {
        return $this->content();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt();
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy();
    }

    public function getSourcePlatform(): string
    {
        return $this->sourcePlatform();
    }

    public function getSourceThreadId(): string
    {
        return $this->sourceThreadId();
    }

    public function getSourceMessageId(): string
    {
        return $this->sourceMessageId();
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf('Memory #%s', $this->id ?? 'new');
    }
}
