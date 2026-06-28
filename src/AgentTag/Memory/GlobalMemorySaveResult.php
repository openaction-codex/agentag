<?php

namespace App\AgentTag\Memory;

use App\Entity\GlobalMemory;

final readonly class GlobalMemorySaveResult
{
    private function __construct(
        private ?GlobalMemory $memory,
        private string $message,
    ) {
    }

    public static function stored(GlobalMemory $memory): self
    {
        return new self($memory, '');
    }

    public static function refused(string $message): self
    {
        return new self(null, $message);
    }

    public function memory(): ?GlobalMemory
    {
        return $this->memory;
    }

    public function message(): string
    {
        return $this->message;
    }
}
