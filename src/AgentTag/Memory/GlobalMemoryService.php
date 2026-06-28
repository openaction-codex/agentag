<?php

namespace App\AgentTag\Memory;

use App\AgentTag\Security\SensitiveTextRedactor;
use App\Entity\GlobalMemory;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GlobalMemoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SensitiveTextRedactor $redactor,
    ) {
    }

    public function rememberExplicit(string $content, GlobalMemoryCommandContext $context): GlobalMemorySaveResult
    {
        $redacted = trim($this->redactor->redact($content));
        if ('' === $redacted) {
            return GlobalMemorySaveResult::refused('I did not store an empty memory.');
        }

        if ($this->isOnlyRedactedSecret($redacted)) {
            return GlobalMemorySaveResult::refused('I did not store that memory because it only contains a sensitive value.');
        }

        $memory = new GlobalMemory(
            $redacted,
            new \DateTimeImmutable(),
            $this->required($context->userId(), 'user id'),
            $this->required($context->platform(), 'platform'),
            $this->required($context->threadId(), 'thread id'),
            $this->required($context->messageId(), 'message id'),
        );
        $this->entityManager->persist($memory);
        $this->entityManager->flush();

        return GlobalMemorySaveResult::stored($memory);
    }

    /**
     * @return list<GlobalMemory>
     */
    public function all(): array
    {
        return $this->entityManager->getRepository(GlobalMemory::class)->findBy([], ['id' => 'ASC']);
    }

    public function delete(int $id): bool
    {
        $memory = $this->entityManager->getRepository(GlobalMemory::class)->find($id);
        if (!$memory instanceof GlobalMemory) {
            return false;
        }

        $this->entityManager->remove($memory);
        $this->entityManager->flush();

        return true;
    }

    public function proposalPrompt(string $content): string
    {
        $redacted = trim($this->redactor->redact($content));
        if ('' === $redacted || $this->isOnlyRedactedSecret($redacted)) {
            return 'I did not propose saving that memory because it appears to contain only a sensitive value.';
        }

        return sprintf('I can remember this only with confirmation: `%s`. Reply with `remember %s` to store it.', $redacted, $redacted);
    }

    private function required(string $value, string $field): string
    {
        $value = trim($value);
        if ('' === $value) {
            throw new \InvalidArgumentException(sprintf('Global memory %s must not be blank.', $field));
        }

        return $value;
    }

    private function isOnlyRedactedSecret(string $content): bool
    {
        return 1 === preg_match('/^(?:[A-Za-z0-9_-]+\s*[:=]\s*)?\[REDACTED\]$/', $content);
    }
}
