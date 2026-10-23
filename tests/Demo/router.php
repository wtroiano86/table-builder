<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\Foundation\Application as Testbench;
use Wtroiano\TableBuilder\Providers\TableBuilderServiceProvider;
use Wtroiano\TableBuilder\Tests\Demo\DemoServiceProvider;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$directory = $argv[1];
putenv('TABLE_DEMO_STORAGE='.$directory);
$input = json_decode(file_get_contents('php://stdin'), true, flags: JSON_THROW_ON_ERROR);
$server = [];
foreach ($input['headers'] as $name => $value) {
    $key = mb_strtoupper(str_replace('-', '_', $name));
    $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_'.$key] = $value;
}

$cookies = [];
foreach (explode(';', $input['headers']['cookie'] ?? '') as $cookie) {
    $pair = explode('=', mb_trim($cookie), 2);
    if (count($pair) === 2) {
        $cookies[$pair[0]] = urldecode($pair[1]);
    }
}

$request = Request::create($input['url'], $input['method'], cookies: $cookies, server: $server, content: $input['body']);

$app = Testbench::create(
    resolvingCallback: static function (Application $app) use ($directory): void {
        $app->useStoragePath($directory.'/storage');
        $app->useBootstrapPath($directory.'/bootstrap');
    },
    options: ['extra' => [
        'dont-discover' => ['*'],
        'providers' => [DemoServiceProvider::class, TableBuilderServiceProvider::class, ExcelServiceProvider::class],
    ]],
);

$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
// A header frame followed by the unmodified response body, including binary exports.
echo json_encode(['status' => $response->getStatusCode(), 'headers' => $response->headers->all()], JSON_THROW_ON_ERROR)."\n";
$response->sendContent();
$kernel->terminate($request, $response);
