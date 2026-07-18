<?php

namespace App\MessageHandler;

use App\AgentTag\Mattermost\MattermostNotifier;
use App\AgentTag\Mattermost\MattermostRunProgressSinkFactory;
use App\AgentTag\Mattermost\TaskCardRenderer;
use App\AgentTag\Runner\TaskModelSelector;
use App\AgentTag\Session\SessionModelSelectionResolver;
use App\Entity\AgentRun;
use App\Message\PrepareAgentTaskMessage;
use App\Message\RunAgentRunMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class PrepareAgentTaskMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionModelSelectionResolver $modelSelectionResolver,
        private TaskModelSelector $modelSelector,
        private TaskCardRenderer $renderer,
        private MattermostNotifier $notifier,
        private MattermostRunProgressSinkFactory $progressSinkFactory,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PrepareAgentTaskMessage $message): void
    {
        $run = $this->entityManager->getRepository(AgentRun::class)->find($message->runId());
        if (!$run instanceof AgentRun) {
            return;
        }
        if ($run->interruptionRequested() && AgentRun::INTERRUPT_CANCEL === $run->interruptionKind()) {
            $run->markInterrupted('Task cancelled before workspace investigation started.', $run->workspacePath());
            $this->entityManager->flush();
            $this->progressSinkFactory->create($run)->finish();

            return;
        }
        if (AgentRun::STATUS_ACCEPTED !== $run->status()) {
            return;
        }
        $workspace = $run->workspacePath();
        if (null === $workspace) {
            throw new \RuntimeException(sprintf('Task #%d has no workspace.', $message->runId()));
        }

        $modelSelection = $this->modelSelectionResolver->resolve($run->session(), $run)
            ?? $this->modelSelector->select($run->inputSummary() ?? 'Handle the Mattermost request.');
        $run->session()->selectModel($modelSelection);
        $run->selectModel($modelSelection);
        $card = $this->renderer->render($run);
        if (null === $run->taskPostId()) {
            $postId = $this->notifier->createPost($this->progressSinkFactory->eventFor($run), $card->message, $card->props);
            if (null !== $postId) {
                $run->assignTaskPost($postId);
            }
        } else {
            $this->notifier->updatePost($run->taskPostId(), $card->message, $card->props);
        }
        $this->entityManager->flush();
        $this->messageBus->dispatch(new RunAgentRunMessage($message->runId()));
    }
}
