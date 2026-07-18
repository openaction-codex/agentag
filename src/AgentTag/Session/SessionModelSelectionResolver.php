<?php

namespace App\AgentTag\Session;

use App\AgentTag\Runner\TaskModelSelection;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SessionModelSelectionResolver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function resolve(ChatSession $session, ?AgentRun $excludedRun = null): ?TaskModelSelection
    {
        $selection = $session->modelSelection();
        if (null !== $selection) {
            return $selection;
        }
        if (null === $session->id()) {
            return null;
        }

        $queryBuilder = $this->entityManager->getRepository(AgentRun::class)->createQueryBuilder('run')
            ->andWhere('run.session = :session')
            ->andWhere('run.modelRoute IS NOT NULL')
            ->andWhere('run.modelSelectionReason IS NOT NULL')
            ->setParameter('session', $session)
            ->orderBy('run.id', 'ASC')
            ->setMaxResults(1);
        if (null !== $excludedRun?->id()) {
            $queryBuilder
                ->andWhere('run.id != :excludedRunId')
                ->setParameter('excludedRunId', $excludedRun->id());
        }

        $firstRun = $queryBuilder->getQuery()->getOneOrNullResult();
        if (!$firstRun instanceof AgentRun || !$firstRun->hasModelSelection()) {
            return null;
        }

        $selection = $firstRun->modelSelection();
        $session->selectModel($selection);

        return $selection;
    }
}
