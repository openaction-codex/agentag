<?php

namespace App\AgentTag\Runner;

interface TaskModelSelector
{
    public function select(string $request): TaskModelSelection;
}
