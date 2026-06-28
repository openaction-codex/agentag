<?php

namespace App\Command;

use App\AgentTag\Memory\GlobalMemoryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:memories:list', description: 'List explicit global memories.')]
final class ListMemoriesCommand extends Command
{
    public function __construct(private readonly GlobalMemoryService $memoryService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $memories = $this->memoryService->all();

        if ([] === $memories) {
            $io->note('No explicit global memories are stored.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($memories as $memory) {
            $rows[] = [
                $memory->id(),
                $memory->content(),
                $memory->createdBy(),
                $memory->sourcePlatform(),
                $memory->sourceThreadId(),
                $memory->sourceMessageId(),
                $memory->createdAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        $io->table(['ID', 'Content', 'Created by', 'Platform', 'Thread', 'Message', 'Created at'], $rows);

        return Command::SUCCESS;
    }
}
