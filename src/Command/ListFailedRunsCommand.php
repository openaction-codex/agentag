<?php

namespace App\Command;

use App\Entity\AgentRun;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:runs:failed', description: 'List failed AgentTag runs with sanitized debug metadata.')]
final class ListFailedRunsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $runs = $this->entityManager->getRepository(AgentRun::class)->findBy(['status' => 'failed'], ['id' => 'DESC'], 50);

        if ([] === $runs) {
            $io->success('No failed runs found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($runs as $run) {
            $rows[] = [
                $run->id(),
                $run->session()->sessionKey(),
                $run->workflowName() ?? '',
                $run->sourceEventId() ?? '',
                $run->requesterId() ?? '',
                $run->exitCode() ?? '',
                $run->workspacePath() ?? '',
                $this->shorten($run->logSummary() ?? $run->outputSummary() ?? ''),
            ];
        }

        $io->table(['Run', 'Session', 'Workflow', 'Source event', 'Requester', 'Exit', 'Workspace', 'Summary'], $rows);

        return Command::SUCCESS;
    }

    private function shorten(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        return strlen($value) > 120 ? substr($value, 0, 117).'...' : $value;
    }
}
