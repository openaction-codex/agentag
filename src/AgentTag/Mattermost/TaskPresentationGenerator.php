<?php

namespace App\AgentTag\Mattermost;

interface TaskPresentationGenerator
{
    public function generate(string $request, string $workingDirectory): TaskPresentation;
}
