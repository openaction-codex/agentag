<?php

namespace App\Command;

use App\AgentTag\Workflow\WorkflowCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:tools:list', description: 'List tool names referenced by configured workflows.')]
final class ListToolsCommand extends Command
{
    public function __construct(private readonly WorkflowCatalog $workflowCatalog)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $tools = $this->workflowCatalog->toolNames();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $tools) {
            $io->note('No tools referenced by configured workflows.');

            return Command::SUCCESS;
        }

        $io->listing($tools);

        return Command::SUCCESS;
    }
}
