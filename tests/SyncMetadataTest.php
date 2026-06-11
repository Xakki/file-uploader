<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xakki\FileUploader\Auth\NullUserResolver;
use Xakki\FileUploader\Clock\SystemClock;
use Xakki\FileUploader\FileManager;
use Xakki\FileUploader\Storage\FlysystemStorage;

class SyncMetadataTest extends TestCase
{
    private string $root;

    private FlysystemStorage $storage;

    private FileManager $manager;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/fu-sync-'.bin2hex(random_bytes(6));
        $this->storage = new FlysystemStorage(new Filesystem(new LocalFilesystemAdapter($this->root)));
        $this->manager = new FileManager([
            'disk' => 'test',
            'directory' => 'uploads',
            'temporary_directory' => '.chunks',
            'metadata_directory' => '.meta',
            'trash_directory' => '.trash',
            'allowed_extensions' => [],
            'soft_delete' => true,
            'trash_ttl_days' => 30,
            'allow_delete_all_files' => true,
            'full_access' => ['users' => [], 'roles' => []],
        ], $this->storage, new NullUserResolver, new NullLogger, new SystemClock);
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->root)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function test_creates_metadata_for_orphan_files(): void
    {
        $content = 'orphan body';
        $this->storage->write('uploads/orphan.txt', $content);

        $result = $this->manager->syncMetadata();

        self::assertSame(1, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(0, $result['deleted']);
        self::assertTrue($this->storage->exists('.meta/'.hash('sha256', $content).'.json'));
    }

    public function test_updates_stale_metadata_for_existing_file(): void
    {
        $content = 'known body';
        $hash = hash('sha256', $content);
        $this->storage->write('uploads/known.txt', $content);
        $this->storage->write('.meta/'.$hash.'.json', (string) json_encode([
            'id' => $hash,
            'name' => 'known.txt',
            'size' => 1, // stale; real size differs
            'mime' => 'text/plain',
            'path' => 'uploads/known.txt',
            'disk' => 'test',
            'hash' => $hash,
            'createdAt' => '2026-01-01T00:00:00+00:00',
            'deletedAt' => null,
            'trashPath' => null,
            'url' => null,
            'userId' => null,
        ]));

        $result = $this->manager->syncMetadata();

        self::assertSame(0, $result['created']);
        self::assertSame(1, $result['updated']);
        self::assertSame(0, $result['deleted']);
        self::assertSame(strlen($content), $this->manager->readMetadata($hash)->size);
    }

    public function test_deletes_metadata_when_file_is_missing(): void
    {
        $this->storage->write('.meta/ghost.json', (string) json_encode([
            'id' => 'ghost',
            'name' => 'ghost.txt',
            'size' => 10,
            'mime' => 'text/plain',
            'path' => 'uploads/ghost.txt', // no such file, no trash copy
            'disk' => 'test',
            'hash' => 'ghost',
            'createdAt' => '2026-01-01T00:00:00+00:00',
            'deletedAt' => null,
            'trashPath' => null,
            'url' => null,
            'userId' => null,
        ]));

        $result = $this->manager->syncMetadata();

        self::assertSame(0, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(1, $result['deleted']);
        self::assertFalse($this->storage->exists('.meta/ghost.json'));
    }
}
