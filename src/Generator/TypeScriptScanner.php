<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Generator;

use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Throwable;

final readonly class TypeScriptScanner
{
    public function __construct(
        private TypeInspector $inspector,
        private Filesystem $files,
        private string $basePath,
    ) {}

    /**
     * Scan the given base-relative directories for TypeScript-exportable classes.
     *
     * @param  array<string>  $directories  Paths relative to $basePath
     * @param  array<string>  $include  Glob-style class name patterns
     * @param  array<string>  $exclude  Glob-style class name patterns
     * @return array<string, array<string, string>>
     */
    public function scan(array $directories, array $include = ['*'], array $exclude = []): array
    {
        $interfaces = [];

        foreach ($directories as $dir) {
            $path = $this->basePath.DIRECTORY_SEPARATOR.$dir;
            if (! $this->files->exists($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->resolveClassName($file->getPathname());
                if (! $className) {
                    continue;
                }

                if (! class_exists($className)) {
                    continue;
                }

                $ref = new ReflectionClass($className);
                if ($ref->isAbstract() || $ref->isInterface()) {
                    continue;
                }

                $baseName = class_basename($className);

                if (! $this->shouldInclude($baseName, $include, $exclude)) {
                    continue;
                }

                try {
                    $definition = $this->inspector->inspect($className);
                    if ($definition !== null) {
                        $interfaces[$baseName] = $definition;
                    }
                } catch (Throwable) {
                    // Skip classes that cannot be introspected
                }
            }
        }

        return $interfaces;
    }

    public function resolveClassName(string $filePath): ?string
    {
        $content = $this->files->get($filePath);

        if (! preg_match('/namespace\s+([^;]+);/', $content, $ns)) {
            return null;
        }

        if (! preg_match('/class\s+(\w+)/', $content, $class)) {
            return null;
        }

        return $ns[1].'\\'.$class[1];
    }

    private function shouldInclude(string $className, array $include, array $exclude): bool
    {
        foreach ($exclude as $pattern) {
            if ($this->matchesPattern($className, $pattern)) {
                return false;
            }
        }

        foreach ($include as $pattern) {
            if ($pattern === '*' || $this->matchesPattern($className, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $className, string $pattern): bool
    {
        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';

        return preg_match($regex, $className) === 1;
    }
}
