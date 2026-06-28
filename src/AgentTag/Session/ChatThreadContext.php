<?php

namespace App\AgentTag\Session;

final readonly class ChatThreadContext
{
    /**
     * @var list<ChatThreadMessage>
     */
    private array $messages;

    /**
     * @param list<ChatThreadMessage> $messages
     */
    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    public static function single(string $externalId, string $authorId, string $text): self
    {
        return new self([
            new ChatThreadMessage($externalId, $authorId, $text),
        ]);
    }

    /**
     * @return list<ChatThreadMessage>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
