<?php

namespace App\AgentTag\Mattermost;

interface MattermostNotifier
{
    public function showTyping(MattermostInboundEvent $event): void;

    public function postProgress(MattermostInboundEvent $event, string $message): void;

    public function uploadFile(MattermostInboundEvent $event, string $path): ?string;

    /**
     * @param array<string, mixed> $props
     * @param list<string>         $fileIds
     */
    public function createPost(MattermostInboundEvent $event, string $message, array $props = [], array $fileIds = []): ?string;

    /** @param array<string, mixed> $props */
    public function updatePost(string $postId, string $message, array $props = []): bool;
}
