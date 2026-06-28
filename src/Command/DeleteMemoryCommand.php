<?php

namespace App\Command;

use App\AgentTag\Memory\GlobalMemoryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:memories:delete', description: 'Delete an explicit global memory by ID.')]
final class DeleteMemoryCommand extends Command
{
    public function __construct(private readonly GlobalMemoryService $memoryService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Global memory ID.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $idArgument = $input->getArgument('id');
        if (!is_string($idArgument) || !ctype_digit($idArgument)) {
            $io->error('Memory ID must be a positive integer.');

            return Command::FAILURE;
        }

        $id = (int) $idArgument;

        if ($id < 1) {
            $io->error('Memory ID must be a positive integer.');

            return Command::FAILURE;
        }

        if (!$this->memoryService->delete($id)) {
            $io->error(sprintf('Global memory #%d was not found.', $id));

            return Command::FAILURE;
        }

        $io->success(sprintf('Deleted global memory #%d.', $id));

        return Command::SUCCESS;
    }
}
