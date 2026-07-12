<?php

namespace App\AgentTag\Mattermost;

interface MattermostNotifier
{
    public function showTyping(MattermostInboundEvent $event): void;

    public function postProgress(MattermostInboundEvent $event, string $message): void;

    /** @param array<string, mixed> $props */
    public function createPost(MattermostInboundEvent $event, string $message, array $props = []): ?string;

    /** @param array<string, mixed> $props */
    public function updatePost(string $postId, string $message, array $props = []): bool;
}
