<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakePacketCommand extends Command
{
    protected $signature = 'make:packet
        {name       : Name of the DataPacket subclass (e.g. OrderPacket)}
        {--data=    : Name of the companion Data class (defaults to {name} with Packet → Data)}
        {--path=    : App-relative path for the packet (default: Domain/Packets)}
        {--data-path= : App-relative path for the Data class (default: Domain/Data)}';

    protected $description = 'Scaffold a typed DataPacket subclass and its companion laravel-data Data class';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $packetName = Str::studly($this->argument('name'));
        $dataName = Str::studly($this->option('data') ?? Str::replaceLast('Packet', 'Data', $packetName));

        $packetPath = $this->option('path') ?? 'Domain/Packets';
        $dataPath = $this->option('data-path') ?? 'Domain/Data';

        $packetNamespace = $this->pathToNamespace($packetPath);
        $dataNamespace = $this->pathToNamespace($dataPath);

        $dataFile = app_path("{$dataPath}/{$dataName}.php");
        $packetFile = app_path("{$packetPath}/{$packetName}.php");

        $this->writeFile(
            $dataFile,
            $this->renderStub('data', [
                'namespace' => $dataNamespace,
                'class'     => $dataName,
            ]),
            "{$dataName}.php",
        );

        $this->writeFile(
            $packetFile,
            $this->renderStub('data-packet', [
                'namespace'  => $packetNamespace,
                'data_fqcn'  => $dataNamespace.'\\'.$dataName,
                'data_class' => $dataName,
                'class'      => $packetName,
            ]),
            "{$packetName}.php",
        );

        $this->info('Done. Run <comment>php artisan typewriter:typegen --packets-only</comment> after filling in the Data class properties.');

        return self::SUCCESS;
    }

    private function writeFile(string $path, string $content, string $label): void
    {
        if ($this->files->exists($path)) {
            $this->warn("  Already exists — skipping {$label}");

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        $this->info("  Created {$label} → {$path}");
    }

    private function renderStub(string $stub, array $replacements): string
    {
        $content = $this->files->get(__DIR__.'/../../resources/stubs/'.$stub.'.stub');

        foreach ($replacements as $key => $value) {
            $content = str_replace('{{ '.$key.' }}', $value, $content);
        }

        return $content;
    }

    private function pathToNamespace(string $path): string
    {
        return 'App\\'.str_replace('/', '\\', $path);
    }
}
