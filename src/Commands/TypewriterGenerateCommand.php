<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Commands;

use Illuminate\Console\Command;

final class TypewriterGenerateCommand extends Command
{
    protected $signature = 'typewriter:generate
        {--types-only    : Skip wayfinder:generate, only generate TypeScript types}
        {--routes-only   : Skip typegen, only run wayfinder:generate}
        {--packets-only  : Only generate DataPacket and routing enum types (passed through to typegen)}
        {--no-barrel     : Skip writing the barrel index.ts (passed through to typegen)}';

    protected $description = 'Generate all TypeScript types: Wayfinder route functions + domain/DTO/DataPacket types';

    public function handle(): int
    {
        if (! $this->option('types-only')) {
            $this->runWayfinder();
        }

        if (! $this->option('routes-only')) {
            $this->runTypegen();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function runWayfinder(): void
    {
        if (! $this->getApplication()->has('wayfinder:generate')) {
            $this->warn('laravel/wayfinder not installed — skipping route generation.');

            return;
        }

        $this->info('<fg=blue>Running wayfinder:generate...</>');
        $this->call('wayfinder:generate');
    }

    private function runTypegen(): void
    {
        $this->info('<fg=blue>Running typewriter:typegen...</>');

        $options = [];
        if ($this->option('no-barrel')) {
            $options['--no-barrel'] = true;
        }
        if ($this->option('packets-only')) {
            $options['--packets-only'] = true;
        }

        $this->call('typewriter:typegen', $options);
    }
}
