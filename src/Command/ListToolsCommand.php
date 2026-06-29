<?php

namespace App\Command;

use App\AgentTag\Tool\ToolCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:tools:list', description: 'List tools configured in the AgentTag workspace.')]
final class ListToolsCommand extends Command
{
    public function __construct(private readonly ToolCatalog $toolCatalog)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $tools = $this->toolCatalog->all();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $tools) {
            $io->note('No tools configured.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($tools as $tool) {
            $rows[] = [
                $tool->name(),
                $tool->type(),
                $tool->workingDirectory(),
                implode(', ', $tool->environmentWhitelist()),
                $tool->timeoutSeconds(),
                $tool->sensitivity(),
                $tool->confirmationPolicy(),
                $tool->sandbox(),
            ];
        }

        $io->table(['Name', 'Type', 'Cwd', 'Env', 'Timeout', 'Sensitivity', 'Confirm', 'Sandbox'], $rows);

        return Command::SUCCESS;
    }
}
