<?php

namespace App\AgentTag\Workflow;

use App\AgentTag\Configuration\AgentTagSettings;
use Symfony\Component\Yaml\Yaml;

final readonly class WorkflowCatalog
{
    public function __construct(private AgentTagSettings $settings)
    {
    }

    /**
     * @return list<WorkflowDefinition>
     */
    public function all(): array
    {
        $path = $this->settings->workflowsPath();
        if (!is_dir($path)) {
            throw new \RuntimeException(sprintf('Workflow directory "%s" does not exist.', $path));
        }

        $files = array_merge(glob($path.'/*.yaml') ?: [], glob($path.'/*.yml') ?: []);
        sort($files);

        $workflows = [];
        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (!is_array($data)) {
                throw new \InvalidArgumentException(sprintf('Workflow file "%s" must contain a YAML mapping.', $file));
            }

            $workflows[] = WorkflowDefinition::fromArray($data, $file);
        }

        return $workflows;
    }

    /**
     * @return list<string>
     */
    public function toolNames(): array
    {
        $tools = [];
        foreach ($this->all() as $workflow) {
            foreach ($workflow->tools() as $tool) {
                $tools[$tool] = true;
            }
        }

        $names = array_keys($tools);
        sort($names);

        return $names;
    }
}
