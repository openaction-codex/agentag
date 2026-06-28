<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Configuration\AgentTagSettings;

final readonly class MattermostMentionDetector
{
    public function __construct(private AgentTagSettings $settings)
    {
    }

    public function isMentioned(string $text): bool
    {
        $tag = preg_quote($this->settings->tag(), '/');

        return 1 === preg_match('/(?<![A-Za-z0-9_@])'.$tag.'(?![A-Za-z0-9_-])/i', $text);
    }
}
