<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Runner\TaskModelSelection;

final readonly class TaskPresentation
{
    public TaskModelSelection $modelSelection;

    public function __construct(
        public string $title,
        public string $acknowledgement,
        ?TaskModelSelection $modelSelection = null,
    ) {
        $this->modelSelection = $modelSelection ?? TaskModelSelection::mainLuna();
    }
}
