<?php

namespace App\AgentTag\Tool;

use App\AgentTag\Configuration\AgentTagSettings;
use Symfony\Component\Yaml\Yaml;

final readonly class ToolCatalog
{
    public function __construct(private AgentTagSettings $settings)
    {
    }

    /**
     * @return list<ToolDefinition>
     */
    public function all(): array
    {
        $path = $this->settings->workspacePath().'/tools';
        if (!is_dir($path)) {
            return [];
        }

        $files = array_merge(glob($path.'/*.yaml') ?: [], glob($path.'/*.yml') ?: []);
        sort($files);

        $tools = [];
        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (!is_array($data)) {
                throw new \InvalidArgumentException(sprintf('Tool file "%s" must contain a YAML mapping.', $file));
            }

            $tools[] = ToolDefinition::fromArray($this->stringKeyedData($data, $file), $file);
        }

        return $tools;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_map(static fn (ToolDefinition $tool): string => $tool->name(), $this->all());
        sort($names);

        return $names;
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function stringKeyedData(array $data, string $sourcePath): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException(sprintf('Tool file "%s" must use string keys.', $sourcePath));
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
