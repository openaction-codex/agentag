<?php

namespace App\Controller\Admin;

final readonly class AdminRedactedValue implements \Stringable
{
    public function __construct(private string $value)
    {
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
