<?php

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Facade;

$container = new Container;

Container::setInstance($container);
Facade::setFacadeApplication($container);

$storagePath = __DIR__.'/storage';
$defaultDiskPath = $storagePath.'/app';
$filesDiskPath = $storagePath.'/files';

foreach ([$defaultDiskPath, $filesDiskPath] as $path) {
    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

if (! \method_exists($container, 'configPath')) {
    // Минимальный контейнер с методом configPath, чтобы phpstan/larastan не падали
    $container = new class extends Container
    {
        protected function joinPath(string $root, string $path = ''): string
        {
            if ($path === '' || $path === null) {
                return $root;
            }

            return $root.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
        }

        // Base path (repo root where this file lives)
        public function basePath(string $path = ''): string
        {
            $base = __DIR__;

            return $this->joinPath($base, $path);
        }

        public function configPath(string $path = ''): string
        {
            return $this->joinPath($this->basePath('config'), $path);
        }

        public function databasePath(string $path = ''): string
        {
            return $this->joinPath($this->basePath('database'), $path);
        }
    };

    Container::setInstance($container);
}

$container->instance('config', new Repository([
    'filesystems' => [
        'default' => 'local',
        'cloud' => 'local',
        'disks' => [
            'public' => [
                'driver' => 'local',
                'root' => $defaultDiskPath,
                'throw' => false,
            ],
        ],
        'links' => [],
    ],
]));

$container->singleton('files', function () {
    return new Filesystem;
});

$container->singleton('filesystem', function ($app) {
    return new FilesystemManager($app);
});

$container->bind('filesystem.disk', function ($app) {
    return $app['filesystem']->disk($app['config']->get('filesystems.default'));
});

$container->bind('filesystem.cloud', function ($app) {
    return $app['filesystem']->disk($app['config']->get('filesystems.cloud'));
});
