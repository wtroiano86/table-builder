<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Commands;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

final readonly class TableClassLocationResolver
{
    public function __construct(private Application $app, private Filesystem $files) {}

    /** @return array{directory: string, namespace: string} */
    public function resolve(string $path, string $rootNamespace): array
    {
        $path = $this->canonicalize($path);
        $appPath = $this->canonicalize($this->app->path());
        $rootNamespace = mb_trim($rootNamespace, '\\');

        if (preg_match('/^app(?:\/|$)/i', $path)) {
            $directory = $appPath.'/'.mb_substr($path, min(4, mb_strlen($path)));
        } elseif ($this->isAbsolute($path)) {
            $directory = $path;
        } elseif (is_dir($this->app->basePath(explode('/', $path)[0]))) {
            $directory = $this->app->basePath($path);
        } else {
            $directory = $appPath.'/'.$path;
        }

        $directory = $this->canonicalize($directory);

        if ($directory === $appPath || str_starts_with($directory, $appPath.'/')) {
            $relative = mb_ltrim(mb_substr($directory, mb_strlen($appPath)), '/');
            $namespace = $rootNamespace.($relative === '' ? '' : '\\'.str_replace('/', '\\', $relative));
        } else {
            $namespace = $this->autoloadNamespace($directory)
                ?? throw new InvalidArgumentException('A table path outside app must belong to a Composer PSR-4 namespace.');
        }

        return ['directory' => $directory, 'namespace' => $namespace];
    }

    private function autoloadNamespace(string $directory): ?string
    {
        $composerPath = $this->app->basePath('composer.json');

        if (! $this->files->exists($composerPath)) {
            return null;
        }

        $composer = json_decode($this->files->get($composerPath), true);

        if (! is_array($composer)) {
            return null;
        }

        $namespace = null;
        $matchedLength = -1;

        foreach (['autoload', 'autoload-dev'] as $section) {
            $configuration = $composer[$section] ?? null;
            $mappings = is_array($configuration) ? ($configuration['psr-4'] ?? null) : null;

            if (! is_array($mappings)) {
                continue;
            }

            foreach ($mappings as $prefix => $paths) {
                if (! is_string($prefix)) {
                    continue;
                }

                foreach (is_array($paths) ? $paths : [$paths] as $path) {
                    if (! is_string($path)) {
                        continue;
                    }

                    $path = str_replace('\\', '/', $path);
                    $base = $this->canonicalize($this->isAbsolute($path) ? $path : $this->app->basePath($path));

                    if (mb_strlen($base) <= $matchedLength || ($directory !== $base && ! str_starts_with($directory, $base.'/'))) {
                        continue;
                    }

                    $relative = mb_ltrim(mb_substr($directory, mb_strlen($base)), '/');
                    $namespace = mb_rtrim($prefix, '\\').($relative === '' ? '' : '\\'.str_replace('/', '\\', $relative));
                    $matchedLength = mb_strlen($base);
                }
            }
        }

        return $namespace;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:\//', $path);
    }

    private function canonicalize(string $path): string
    {
        $path = mb_trim(str_replace('\\', '/', $path));
        $prefix = str_starts_with($path, '/') ? '/' : (preg_match('/^[A-Za-z]:\//', $path) ? mb_substr($path, 0, 3) : '');
        $segments = [];

        foreach (explode('/', mb_substr($path, mb_strlen($prefix))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);
            } elseif ($segment !== '..' || $prefix === '') {
                $segments[] = $segment;
            }
        }

        return $prefix.implode('/', $segments);
    }
}
