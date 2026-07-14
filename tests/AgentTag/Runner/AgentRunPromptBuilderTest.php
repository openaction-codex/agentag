<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Runner\AgentRunPromptBuilder;
use App\AgentTag\Runner\TaskModelSelection;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use PHPUnit\Framework\TestCase;

final class AgentRunPromptBuilderTest extends TestCase
{
    public function testItKeepsSimpleWorkInTheLunaMaxMainAgent(): void
    {
        $run = $this->taskRun(TaskModelSelection::mainLuna('Straightforward wording change with known context.'));

        $prompt = (new AgentRunPromptBuilder())->build($run);

        self::assertStringContainsString('Use GPT-5.6 Luna with max reasoning directly in this main agent.', $prompt);
        self::assertStringContainsString('Straightforward wording change with known context.', $prompt);
        self::assertStringContainsString('Do not delegate', $prompt);
    }

    public function testItRestrictsResponsesToFrenchOrEnglishWithFrenchAsTheDefault(): void
    {
        $prompt = (new AgentRunPromptBuilder())->build($this->taskRun(TaskModelSelection::mainLuna()));

        self::assertStringContainsString('Answer only in French or English.', $prompt);
        self::assertStringContainsString('Answer in English only when the latest user message is confidently determined to be English.', $prompt);
        self::assertStringContainsString('mixed, ambiguous, language-neutral, written in another language, or its language is uncertain', $prompt);
    }

    public function testItEnforcesTheSelectedSolSubagentAtTheStart(): void
    {
        $run = $this->taskRun(TaskModelSelection::fromRoute('sol-xhigh', 'Cross-system feature requiring architecture work.'));

        $prompt = (new AgentRunPromptBuilder())->build($run);

        self::assertStringContainsString('GPT-5.6 Sol with xhigh reasoning', $prompt);
        self::assertStringContainsString('project-scoped `sol-xhigh` subagent', $prompt);
        self::assertStringContainsString('Before substantive task work, spawn exactly', $prompt);
        self::assertStringContainsString('without full-history inheritance', $prompt);
        self::assertStringContainsString('Use exactly "Doing: ..." with no Done or Next fields', $prompt);
        self::assertStringContainsString('French-or-English response language selected by the interaction rules', $prompt);
        self::assertStringContainsString('strips the label before displaying the activity', $prompt);
        self::assertStringContainsString('never send timer-based or no-change updates', $prompt);
        self::assertStringContainsString('wait silently between concrete current-activity notes', $prompt);
        self::assertStringContainsString('Luna main agent remains responsible', $prompt);
    }

    public function testAResumedTaskDoesNotBlindlyRespawnItsSpecialist(): void
    {
        $run = $this->taskRun(TaskModelSelection::fromRoute('terra-max', 'Read-heavy synthesis across several documents.'));
        $run->recordCodexThread('thread-id');

        $prompt = (new AgentRunPromptBuilder())->build($run);

        self::assertStringContainsString('Continue using the `terra-max` route.', $prompt);
        self::assertStringContainsString('invoke it again only when the remaining work needs fresh specialist work', $prompt);
    }

    private function taskRun(?TaskModelSelection $selection): AgentRun
    {
        $session = new ChatSession('mattermost:t:c:p', 't', 'c', 'p', new \DateTimeImmutable());
        $run = new AgentRun($session, AgentRun::STATUS_ACCEPTED, new \DateTimeImmutable(), contextSnapshot: 'Task context.');
        $run->initializeTask('Handle task', 'Workspace ready', 'thomas', new \DateTimeImmutable('+1 day'), 2, 60, 'milestones', $selection);

        return $run;
    }
}
