<?php

namespace App\Command;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Workspace\WorkspaceLayout;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:workspace:inspect', description: 'Inspect AgentTag workspace paths and repository configuration.')]
final class InspectWorkspaceCommand extends Command
{
    public function __construct(
        private readonly WorkspaceLayout $workspaceLayout,
        private readonly AgentTagSettings $settings,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $paths = [
            ['workspace template', $this->workspaceLayout->workspacePath(), $this->exists($this->workspaceLayout->workspacePath())],
            ['runtime root', $this->workspaceLayout->runtimeRootPath(), $this->exists($this->workspaceLayout->runtimeRootPath())],
            ['runs', $this->workspaceLayout->runsPath(), $this->exists($this->workspaceLayout->runsPath())],
            ['repository cache', $this->workspaceLayout->repositoryCachePath(), $this->exists($this->workspaceLayout->repositoryCachePath())],
        ];

        $io->table(['Area', 'Path', 'Exists'], $paths);

        $rows = [];
        foreach ($this->settings->repositories() as $repository) {
            $rows[] = [$repository->identifier(), $repository->displayName(), $repository->url()];
        }

        if ([] === $rows) {
            $io->note('No repositories configured.');
        } else {
            $io->table(['Repository', 'Name', 'SSH URL'], $rows);
        }

        return Command::SUCCESS;
    }

    private function exists(string $path): string
    {
        return is_dir($path) ? 'yes' : 'no';
    }
}
