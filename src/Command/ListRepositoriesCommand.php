<?php

namespace App\Command;

use App\AgentTag\Configuration\AgentTagSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:repositories:list', description: 'List repositories configured for AgentTag.')]
final class ListRepositoriesCommand extends Command
{
    public function __construct(private readonly AgentTagSettings $settings)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];

        foreach ($this->settings->repositories() as $repository) {
            $rows[] = [$repository->identifier(), $repository->displayName(), $repository->url()];
        }

        if ([] === $rows) {
            $io->note('No repositories configured.');

            return Command::SUCCESS;
        }

        $io->table(['Identifier', 'Name', 'SSH URL'], $rows);

        return Command::SUCCESS;
    }
}
