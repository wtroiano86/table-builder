<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Wtroiano\TableBuilder\Commands\TableClassLocationResolver;

it('resolves generator directories and namespaces together', function (string $input, string $configured, ?string $override, string $relativePath, string $namespace): void {
    config()->set('table-builder.tables_path', $configured);
    $arguments = ['name' => $input, '--model' => 'User'];
    if ($override !== null) {
        $arguments['--path'] = $override === 'ABSOLUTE' ? app_path('Admin/Tables') : $override;
    }

    $path = app_path($relativePath);
    try {
        expect(Artisan::call('make:table-builder', $arguments))->toBe(0)
            ->and(file_get_contents($path))->toContain('namespace '.$namespace.';', 'use App\\Models\\User;', 'protected ?string $resource = User::class;');
    } finally {
        if (is_file($path)) {
            File::delete($path);
        }
    }
})->with([
    ['ReviewGenerated', 'app/Tables', null, 'Tables/ReviewGenerated.php', 'App\\Tables'],
    ['Nested/ReviewGenerated.php', 'Admin/Tables', null, 'Admin/Tables/Nested/ReviewGenerated.php', 'App\\Admin\\Tables\\Nested'],
    ['ReviewGenerated', 'app/Elsewhere', 'app/Admin/Tables', 'Admin/Tables/ReviewGenerated.php', 'App\\Admin\\Tables'],
    ['ReviewGenerated', 'app/Elsewhere', 'ABSOLUTE', 'Admin/Tables/ReviewGenerated.php', 'App\\Admin\\Tables'],
    ['App\\Explicit\\ReviewGenerated', 'app/Elsewhere', null, 'Explicit/ReviewGenerated.php', 'App\\Explicit'],
    ['app/Explicit/ReviewGenerated.php', 'app/Elsewhere', null, 'Explicit/ReviewGenerated.php', 'App\\Explicit'],
]);

it('uses legacy generator configuration when the canonical option is empty', function (): void {
    config()->set('table-builder.tables_path');
    config()->set('table-builder.generator.path', 'app/Legacy/Tables');

    $path = app_path('Legacy/Tables/ReviewLegacy.php');
    try {
        expect(Artisan::call('make:table-builder', ['name' => 'ReviewLegacy']))->toBe(0)
            ->and(file_get_contents($path))->toContain('namespace App\\Legacy\\Tables;');
    } finally {
        if (is_file($path)) {
            File::delete($path);
        }
    }
});

it('supports project PSR-4 paths and chooses the most specific namespace', function (): void {
    $composerPath = base_path('composer.json');
    $original = file_get_contents($composerPath);
    $composer = json_decode($original, true);
    $directory = base_path('review-domain/Accounts');
    File::ensureDirectoryExists($directory);
    $composer['autoload']['psr-4']['Review\\'] = ['review-domain/'];
    $composer['autoload']['psr-4']['Review\\Accounts\\'] = 'review-domain/Accounts/';
    file_put_contents($composerPath, json_encode($composer));
    $path = $directory.'/Tables/ReviewExternal.php';
    try {
        expect(Artisan::call('make:table-builder', ['name' => 'ReviewExternal', '--path' => 'review-domain/Accounts/Tables']))->toBe(0)
            ->and(file_get_contents($path))->toContain('namespace Review\\Accounts\\Tables;');
    } finally {
        file_put_contents($composerPath, $original);
        File::deleteDirectory(base_path('review-domain'));
    }
});

it('fails safely for external absolute generator paths with missing or malformed Composer configuration', function (?string $json): void {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->with(base_path('composer.json'))->andReturn($json !== null);
    if ($json !== null) {
        $files->shouldReceive('get')->with(base_path('composer.json'))->andReturn($json);
    }

    $resolver = new TableClassLocationResolver(app(), $files);
    expect(fn (): array => $resolver->resolve('/unmapped-tables', 'App\\'))->toThrow(InvalidArgumentException::class, 'must belong to a Composer PSR-4 namespace');
})->with([null, '{invalid', '{"autoload":{}}', '{"autoload":{"psr-4":{"Ignored\\\\":[null],"0":123}},"autoload-dev":{}}']);

it('rejects relative external directories with no matching PSR-4 mapping', function (): void {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->andReturn(false);
    $resolver = new TableClassLocationResolver(app(), $files);
    $directory = base_path('review-unmapped');
    mkdir($directory);
    try {
        expect(fn (): array => $resolver->resolve('review-unmapped/Tables', 'App\\'))
            ->toThrow(InvalidArgumentException::class, 'must belong to a Composer PSR-4 namespace');
    } finally {
        rmdir($directory);
    }
});

it('rejects unmapped external command paths before creating a PHP file', function (): void {
    $directory = base_path('evidence_unmapped');
    File::ensureDirectoryExists($directory);

    try {
        expect(fn (): int => Artisan::call('make:table-builder', ['name' => 'EvidenceGenerated', '--path' => 'evidence_unmapped/Tables']))
            ->toThrow(InvalidArgumentException::class, 'must belong to a Composer PSR-4 namespace');
        expect(File::exists($directory.'/Tables/EvidenceGenerated.php'))->toBeFalse();
    } finally {
        File::deleteDirectory($directory);
    }
});
