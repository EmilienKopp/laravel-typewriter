<?php

declare(strict_types=1);

namespace Splitstack\Typewriter;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Splitstack\Typewriter\Commands\MakePacketCommand;
use Splitstack\Typewriter\Commands\TypegenCommand;
use Splitstack\Typewriter\Commands\TypewriterGenerateCommand;
use Splitstack\Typewriter\Generator\InterfaceWriter;

final class TypewriterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/typewriter.php' => config_path('typewriter.php'),
        ], 'typewriter-config');

        $this->publishes([
            __DIR__.'/../resources/ts/DataPacket.ts' => $this->tsAssetPath(),
        ], 'typewriter-ts');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TypewriterGenerateCommand::class,
                TypegenCommand::class,
                MakePacketCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/typewriter.php', 'typewriter');

        $this->app->singleton(InterfaceWriter::class, function (): InterfaceWriter {
            return new InterfaceWriter(new Filesystem());
        });

        $this->app->singleton(MakePacketCommand::class, function (): MakePacketCommand {
            return new MakePacketCommand(new Filesystem());
        });
    }

    private function tsAssetPath(): string
    {
        return config('typewriter.ts_asset_path')
            ?? resource_path('js/utils/DataPacket.ts');
    }
}
