<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:table-builder')]
#[Description('Create a new Table Builder table class')]
final class MakeTableCommand extends GeneratorCommand
{
    protected $name = 'make:table-builder';

    protected $type = 'Table';

    protected function getStub(): string
    {
        return dirname(__DIR__, 2).'/resources/stubs/table.stub';
    }

    protected function qualifyClass($name): string
    {
        $name = mb_ltrim((string) $name, '\\/');
        $name = str_replace('/', '\\', $name);
        $name = (string) preg_replace('/\.php$/i', '', $name);

        $rootNamespace = $this->rootNamespace();

        if (preg_match('/^app\\\\/i', $name)) {
            $name = $rootNamespace.mb_substr($name, 4);
        }

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        $namespace = $this->getDefaultNamespace(mb_trim($rootNamespace, '\\'));

        return Str::startsWith($name, $namespace.'\\') ? $name : $namespace.'\\'.$name;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->tableLocation($rootNamespace)['namespace'];
    }

    protected function getPath($name): string
    {
        $rawName = (string) $this->getNameInput();

        if (preg_match('/^app[\/\\\\]/i', $rawName) || Str::startsWith($rawName, $this->rootNamespace())) {
            return parent::getPath($name);
        }

        $location = $this->tableLocation($this->rootNamespace());
        $prefix = $location['namespace'].'\\';
        $relative = Str::startsWith($name, $prefix) ? mb_substr($name, mb_strlen($prefix)) : class_basename($name);

        return $location['directory'].'/'.str_replace('\\', '/', $relative).'.php';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return str_replace(
            ['{{ modelImport }}', '{{ modelResource }}'],
            [$this->modelImport(), $this->modelResource()],
            $stub,
        );
    }

    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'The model that the table should use as its resource'],
            ['path', null, InputOption::VALUE_OPTIONAL, 'The directory where the table class should be generated'],
        ];
    }

    /** @return array{directory: string, namespace: string} */
    private function tableLocation(string $rootNamespace): array
    {
        $path = $this->option('path');

        if (! is_string($path) || mb_trim($path) === '') {
            $path = 'app/Tables';

            foreach (['tables_path', 'generator.path', 'generator.directory', 'path', 'directory', 'generator.namespace', 'namespace'] as $key) {
                $configured = config('table-builder.'.$key);

                if (is_string($configured) && mb_trim($configured) !== '') {
                    $path = $configured;
                    break;
                }
            }
        }

        return new TableClassLocationResolver($this->laravel, $this->files)->resolve($path, $rootNamespace);
    }

    private function modelImport(): string
    {
        if (! is_string($this->option('model'))) {
            return '';
        }

        return 'use '.$this->qualifyModel($this->option('model')).';'.PHP_EOL;
    }

    private function modelResource(): string
    {
        if (! is_string($this->option('model'))) {
            return '';
        }

        $model = class_basename($this->qualifyModel($this->option('model')));

        return PHP_EOL
            .'    protected ?string $resource = '.$model.'::class;'.PHP_EOL
            .PHP_EOL;
    }
}
