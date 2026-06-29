<?php

namespace App\Command;

use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Tool\ToolCatalog;
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
        private readonly AgentProfileProvider $agentProfileProvider,
        private readonly ToolCatalog $toolCatalog,
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
            ['Workspace template' => $this->settings->workspacePath()],
            ['Runtime root' => \dirname($this->settings->workspacePath())],
            ['Repositories' => (string) count($this->settings->repositories())],
            ['Run timeout' => (string) $this->settings->runTimeoutSeconds()],
        );

        try {
            $agent = $this->agentProfileProvider->profile();
            $tools = $this->toolCatalog->all();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Configuration is valid. Generic agent `%s` is ready with %d tool(s).',
            $agent->name(),
            count($tools),
        ));

        return Command::SUCCESS;
    }
}
