<?php

namespace App\Command;

use App\AgentTag\Workflow\WorkflowCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:workflows:list', description: 'List workflows loaded by AgentTag.')]
final class ListWorkflowsCommand extends Command
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
            $workflows = $this->workflowCatalog->all();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $rows = [];
        foreach ($workflows as $workflow) {
            $rows[] = [
                $workflow->name(),
                implode(', ', $workflow->triggers()),
                implode(', ', $workflow->tools()),
                $workflow->description(),
            ];
        }

        if ([] === $rows) {
            $io->note('No workflow files found.');

            return Command::SUCCESS;
        }

        $io->table(['Name', 'Triggers', 'Tools', 'Description'], $rows);

        return Command::SUCCESS;
    }
}
