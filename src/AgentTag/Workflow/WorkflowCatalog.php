<?php

namespace App\AgentTag\Workflow;

use App\AgentTag\Configuration\AgentTagSettings;
use Symfony\Component\Yaml\Yaml;

final readonly class WorkflowCatalog
{
    private WorkflowRevisionResolver $revisionResolver;

    public function __construct(
        private AgentTagSettings $settings,
        ?WorkflowRevisionResolver $revisionResolver = null,
    ) {
        $this->revisionResolver = $revisionResolver ?? new NullWorkflowRevisionResolver();
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
        $revision = $this->revisionResolver->revisionFor($path);

        $workflows = [];
        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (!is_array($data)) {
                throw new \InvalidArgumentException(sprintf('Workflow file "%s" must contain a YAML mapping.', $file));
            }

            $workflows[] = WorkflowDefinition::fromArray($data, $file, $revision);
        }

        return $workflows;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(
            static fn (WorkflowDefinition $workflow): string => $workflow->name(),
            $this->all(),
        );
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
