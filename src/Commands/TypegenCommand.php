<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Splitstack\Typewriter\Generator\InterfaceWriter;
use Splitstack\Typewriter\Transformers\DataPacketTransformer;
use Splitstack\Typewriter\Transformers\PlainClassTransformer;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\LaravelTypeScriptTransformerExtension;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;

final class TypegenCommand extends Command
{
    protected $signature = 'typewriter:typegen
        {--value-objects-only : Only generate types for Domain/ValueObjects}
        {--dtos-only          : Only generate types for DTOs and Resources}
        {--packets-only       : Only generate DataPacket and routing enum types}
        {--no-barrel          : Skip writing the barrel index.ts}';

    protected $description = 'Generate TypeScript types from domain classes, DTOs, and DataPackets';

    public function __construct(
        private readonly InterfaceWriter $writer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sources = $this->resolveSources();

        $outputPaths = [];

        foreach ($sources as $sourceKey) {
            $outputPath = $this->resolveOutputPath($sourceKey);

            if (! $outputPath) {
                $this->warn(sprintf("No output path configured for source '%s'. Skipping.", $sourceKey));

                continue;
            }

            $dirs = $this->resolveDirectories($sourceKey);

            if (empty($dirs)) {
                $this->warn(sprintf("No directories configured for source '%s'. Skipping.", $sourceKey));

                continue;
            }

            $this->line(sprintf('Scanning <comment>%s</comment>...', $sourceKey));

            $count = $this->runTransformerPipeline($dirs, $outputPath);

            if ($count === 0) {
                $this->warn(sprintf("  No exportable types found for '%s'.", $sourceKey));

                continue;
            }

            $this->info(sprintf('  Written %d type(s) → %s', $count, $outputPath));
            $outputPaths[] = $outputPath;
        }

        if (empty($outputPaths)) {
            $this->warn('No types generated.');

            return self::SUCCESS;
        }

        if (! $this->option('no-barrel')) {
            $this->writeBarrel($outputPaths);
        }

        return self::SUCCESS;
    }

    private function runTransformerPipeline(array $absoluteDirs, string $outputPath): int
    {
        $outputDir = dirname($outputPath);
        (new Filesystem())->ensureDirectoryExists($outputDir);

        $configFactory = TypeScriptTransformerConfigFactory::create()
            ->outputDirectory($outputDir)
            ->transformDirectories(...$absoluteDirs)
            ->extension(new LaravelTypeScriptTransformerExtension())
            ->extension(new LaravelDataTypeScriptTransformerExtension())
            ->prependTransformer(new DataPacketTransformer())
            ->transformer(new EnumTransformer())
            ->transformer(new PlainClassTransformer())
            ->writer(new FlatModuleWriter($outputPath))
            ->withoutManifest();

        $transformer = TypeScriptTransformer::create($configFactory);
        [$collection] = $transformer->resolveState();
        $transformer->outputTransformed($collection);

        return $collection->count();
    }

    /** @return array<string> */
    private function resolveSources(): array
    {
        $onlyFlags = [
            'value-objects-only' => 'value_objects',
            'dtos-only'          => 'dtos',
            'packets-only'       => 'packets',
        ];

        foreach ($onlyFlags as $flag => $key) {
            if ($this->option($flag)) {
                return [$key];
            }
        }

        $keys = array_keys(config('typewriter.sources', []));

        return array_filter($keys, function (string $key): bool {
            if ($key !== 'packets') {
                return true;
            }

            return (bool) config('typewriter.sources.packets.enabled', false);
        });
    }

    /** @return array<string> Absolute directory paths that exist on disk */
    private function resolveDirectories(string $sourceKey): array
    {
        $config = config('typewriter.sources.'.$sourceKey, []);
        $dirs = $config['directories'] ?? [];
        $enumDirs = $config['enums'] ?? [];

        return array_values(array_filter(
            array_map(
                fn (string $dir): string => app_path($dir),
                array_merge($dirs, $enumDirs),
            ),
            fn (string $path): bool => is_dir($path),
        ));
    }

    private function writeBarrel(array $outputPaths): void
    {
        $barrelPath = $this->resolveOutputPath('barrel')
            ?? resource_path('js/types/index.ts');

        $this->writer->writeBarrel($outputPaths, $barrelPath);
        $this->info('Barrel written → '.$barrelPath);
    }

    private function resolveOutputPath(string $key): ?string
    {
        $configured = config('typewriter.output.'.$key);

        if ($configured) {
            return $configured;
        }

        return match ($key) {
            'value_objects' => resource_path('js/types/value-objects.ts'),
            'dtos'          => resource_path('js/types/dtos.ts'),
            'packets'       => resource_path('js/types/packets.ts'),
            'barrel'        => resource_path('js/types/index.ts'),
            default         => null,
        };
    }
}
