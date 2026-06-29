<?php

namespace App\Command;

use App\AgentTag\Workspace\WorkspaceLayout;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:workspace:inspect', description: 'Inspect AgentTag workspace paths.')]
final class InspectWorkspaceCommand extends Command
{
    public function __construct(
        private readonly WorkspaceLayout $workspaceLayout,
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
        ];

        $io->table(['Area', 'Path', 'Exists'], $paths);

        return Command::SUCCESS;
    }

    private function exists(string $path): string
    {
        return is_dir($path) ? 'yes' : 'no';
    }
}
