<?php

namespace App\Command;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Workflow\WorkflowCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agentag:config:validate', description: 'Validate AgentTag configuration.')]
final class ValidateConfigCommand extends Command
{
    public function __construct(
        private readonly AgentTagSettings $settings,
        private readonly WorkflowCatalog $workflowCatalog,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('AgentTag configuration');
        $io->definitionList(
            ['Tag' => $this->settings->tag()],
            ['Workspace' => $this->settings->workspacePath()],
            ['Workflows' => $this->settings->workflowsPath()],
            ['Repositories' => (string) count($this->settings->repositories())],
        );

        try {
            $workflows = $this->workflowCatalog->all();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Configuration is valid. %d workflow(s) found.', count($workflows)));

        return Command::SUCCESS;
    }
}
