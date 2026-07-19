<?php

namespace App\AgentTag\Mattermost;

interface MattermostInputFileDownloader
{
    /**
     * @param list<string> $postIds
     *
     * @return list<string>
     */
    public function sync(array $postIds, string $inputFilesDirectory): array;
}
