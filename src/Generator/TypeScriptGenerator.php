<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Generator;

final readonly class TypeScriptGenerator
{
    public function __construct(
        private TypeScriptScanner $scanner,
        private InterfaceWriter $writer,
    ) {}

    /**
     * Scan a named source group defined in config('typewriter.sources').
     *
     * @return array<string, array<string, string>>
     */
    public function scanSource(string $sourceKey, ?callable $onProgress = null): array
    {
        $config = config('typewriter.sources.'.$sourceKey, []);
        $directories = $config['directories'] ?? [];
        $include = $config['include'] ?? ['*'];
        $exclude = $config['exclude'] ?? [];
        $optIn = $config['opt_in'] ?? true;

        $interfaces = $this->scanner->scan($directories, $include, $exclude, $optIn);

        if ($onProgress !== null) {
            foreach (array_keys($interfaces) as $name) {
                $onProgress($name);
            }
        }

        return $interfaces;
    }

    /**
     * Write a set of interfaces to a TypeScript file.
     *
     * @param  array<string, array<string, string>>  $interfaces
     * @param  array<string, string[]>  $availableImports  Map of relative import path → exported type names
     */
    public function writeInterfaces(array $interfaces, string $outputPath, array $availableImports = []): void
    {
        $this->writer->writeInterfaces($interfaces, $outputPath, $availableImports);
    }

    /**
     * Write a barrel index.ts that re-exports from the given output files.
     *
     * @param  array<string>  $outputPaths  Absolute paths to the files to barrel
     */
    public function writeBarrel(array $outputPaths, string $barrelPath): void
    {
        $this->writer->writeBarrel($outputPaths, $barrelPath);
    }
}
