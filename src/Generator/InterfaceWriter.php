<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Generator;

use DateTimeImmutable;
use Illuminate\Filesystem\Filesystem;

final class InterfaceWriter
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * Write TypeScript interfaces to a file, with optional cross-imports from sibling files.
     *
     * @param  array<string, array<string, string>>  $interfaces  Map of interface name → property definitions
     * @param  array<string, string[]>  $availableImports  Map of relative path → exported type names
     *                                                     e.g. ['./entities' => ['UserEntity', 'OrderEntity']]
     */
    public function writeInterfaces(array $interfaces, string $outputPath, array $availableImports = []): void
    {
        $this->files->ensureDirectoryExists(dirname($outputPath));
        $this->files->put($outputPath, $this->buildContent($interfaces, $availableImports));
    }

    /**
     * Write a barrel index.ts that re-exports from each given output file.
     * Only includes files that exist and are non-empty.
     *
     * @param  array<string>  $outputPaths  Absolute paths to the files to barrel
     */
    public function writeBarrel(array $outputPaths, string $barrelPath): void
    {
        $exports = [];
        foreach ($outputPaths as $path) {
            if ($this->files->exists($path) && $this->files->size($path) > 0) {
                $basename = pathinfo($path, PATHINFO_FILENAME);
                $exports[] = sprintf("export * from './%s';", $basename);
            }
        }

        if ($exports === []) {
            return;
        }

        $this->files->ensureDirectoryExists(dirname($barrelPath));
        $content = "// Auto-generated — do not edit\n".implode("\n", $exports)."\n";
        $this->files->put($barrelPath, $content);
    }

    public function formatInterface(string $name, string $properties): string
    {
        return "export interface {$name} {\n{$properties}\n}";
    }

    private function buildContent(array $interfaces, array $availableImports): string
    {
        $timestamp = (new DateTimeImmutable())->format(DateTimeImmutable::ATOM);

        $content = "// Auto-generated — do not edit\n";
        $content .= '// Generated: '.$timestamp."\n\n";

        $importLines = $this->resolveImports($interfaces, $availableImports);
        if ($importLines !== []) {
            $content .= implode("\n", $importLines)."\n\n";
        }

        foreach ($interfaces as $name => $properties) {
            $content .= "export interface {$name} {\n";
            foreach ($properties as $prop => $type) {
                $content .= "  {$prop}: {$type};\n";
            }

            $content .= "}\n\n";
        }

        return mb_rtrim($content)."\n";
    }

    /**
     * @param  array<string, array<string, string>>  $interfaces
     * @param  array<string, string[]>  $availableImports
     * @return array<string>
     */
    private function resolveImports(array $interfaces, array $availableImports): array
    {
        if ($availableImports === []) {
            return [];
        }

        $referencedTypes = [];
        foreach ($interfaces as $properties) {
            foreach ($properties as $type) {
                preg_match_all('/\b([A-Z][a-zA-Z0-9]+)\b/', $type, $matches);
                foreach ($matches[1] as $match) {
                    $referencedTypes[$match] = true;
                }
            }
        }

        $importLines = [];
        foreach ($availableImports as $importPath => $exportedNames) {
            $needed = array_values(array_filter(
                $exportedNames,
                fn (string $name): bool => isset($referencedTypes[$name])
            ));
            sort($needed);

            if ($needed !== []) {
                $importLines[] = 'import type { '.implode(', ', $needed).sprintf(" } from '%s';", $importPath);
            }
        }

        return $importLines;
    }
}
