<?php

namespace App\Command;

use App\AgentTag\Workspace\WorkspaceLayout;
use App\Entity\AgentRun;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'agentag:workspace:cleanup', description: 'List or delete old isolated workspace directories without deleting run history.')]
final class CleanupWorkspaceCommand extends Command
{
    public function __construct(
        private readonly WorkspaceLayout $workspaceLayout,
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('older-than-days', null, InputOption::VALUE_REQUIRED, 'Only include directories older than this many days.', '7');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Delete matching directories. Without this flag the command is a dry run.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $daysOption = $input->getOption('older-than-days');
        if (!is_string($daysOption) || !ctype_digit($daysOption)) {
            $io->error('--older-than-days must be a positive integer.');

            return Command::FAILURE;
        }

        $days = (int) $daysOption;
        if ($days < 1) {
            $io->error('--older-than-days must be at least 1.');

            return Command::FAILURE;
        }

        $candidates = $this->candidates($days);
        if ([] === $candidates) {
            $io->success('No workspace directories matched cleanup criteria.');

            return Command::SUCCESS;
        }

        $io->listing($candidates);
        if (true !== $input->getOption('force')) {
            $io->note('Dry run only. Re-run with --force to delete these workspace directories. Database run/session history is never deleted by this command.');

            return Command::SUCCESS;
        }

        foreach ($candidates as $path) {
            $this->filesystem->remove($path);
            $this->markWorkspaceCleaned($path);
        }
        $io->success(sprintf('Deleted %d workspace director%s. Database run/session history was not deleted.', count($candidates), 1 === count($candidates) ? 'y' : 'ies'));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function candidates(int $olderThanDays): array
    {
        $threshold = time() - ($olderThanDays * 86400);
        $paths = [];
        foreach ([$this->workspaceLayout->runsPath(), $this->workspaceLayout->runtimeRootPath().'/artifacts'] as $basePath) {
            foreach (glob($basePath.'/*') ?: [] as $path) {
                if (is_dir($path) && !is_link($path) && (filemtime($path) ?: time()) < $threshold) {
                    $paths[] = $path;
                }
            }
        }
        sort($paths);

        return $paths;
    }

    private function markWorkspaceCleaned(string $path): void
    {
        $run = $this->entityManager->getRepository(AgentRun::class)->findOneBy(['workspacePath' => $path]);
        if (!$run instanceof AgentRun) {
            return;
        }

        $run->markWorkspaceCleaned();
        $this->entityManager->flush();
    }
}
