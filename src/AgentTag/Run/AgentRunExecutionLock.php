<?php

namespace App\AgentTag\Run;

final readonly class AgentRunExecutionLock
{
    public function acquire(int $runId): ?AgentRunExecutionLease
    {
        if ($runId < 1) {
            throw new \InvalidArgumentException('Cannot lock an unpersisted run.');
        }

        $path = sprintf('%s/agentag-run-%d.lock', sys_get_temp_dir(), $runId);
        $handle = fopen($path, 'c');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Unable to open the execution lock for run #%d.', $runId));
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return new AgentRunExecutionLease($handle);
    }
}
