<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Xakki\FileUploader\Storage\FlysystemStorage;

class FlysystemStorageTest extends TestCase
{
    private string $root;

    private FlysystemStorage $storage;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/fu-fs-'.bin2hex(random_bytes(6));
        $this->storage = new FlysystemStorage(new Filesystem(new LocalFilesystemAdapter($this->root)));
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

    public function test_write_read_and_size_round_trip(): void
    {
        self::assertFalse($this->storage->exists('a.txt'));
        self::assertTrue($this->storage->write('a.txt', 'hello'));
        self::assertTrue($this->storage->exists('a.txt'));
        self::assertSame('hello', $this->storage->read('a.txt'));
        self::assertSame(5, $this->storage->size('a.txt'));
    }

    public function test_write_stream_and_read_stream(): void
    {
        $in = fopen('php://temp', 'r+b');
        self::assertIsResource($in);
        fwrite($in, 'streamed');
        rewind($in);

        self::assertTrue($this->storage->writeStream('s.bin', $in));
        if (is_resource($in)) {
            fclose($in);
        }

        $out = $this->storage->readStream('s.bin');
        self::assertIsResource($out);
        self::assertSame('streamed', stream_get_contents($out));
        fclose($out);
    }

    public function test_make_directory_marks_path_as_existing(): void
    {
        self::assertFalse($this->storage->exists('d'));
        self::assertTrue($this->storage->makeDirectory('d'));
        self::assertTrue($this->storage->exists('d'));
    }

    public function test_move_relocates_a_file(): void
    {
        $this->storage->write('from.txt', 'payload');

        self::assertTrue($this->storage->move('from.txt', 'nested/to.txt'));
        self::assertFalse($this->storage->exists('from.txt'));
        self::assertSame('payload', $this->storage->read('nested/to.txt'));
    }

    public function test_delete_file_and_directory(): void
    {
        $this->storage->write('dir/x.txt', 'x');
        self::assertTrue($this->storage->delete('dir/x.txt'));
        self::assertFalse($this->storage->exists('dir/x.txt'));

        $this->storage->write('dir2/y.txt', 'y');
        self::assertTrue($this->storage->deleteDirectory('dir2'));
        self::assertFalse($this->storage->exists('dir2/y.txt'));
    }

    public function test_files_is_non_recursive_and_all_files_is_recursive(): void
    {
        $this->storage->write('box/a.txt', 'a');
        $this->storage->write('box/b.txt', 'b');
        $this->storage->write('box/sub/c.txt', 'c');

        $files = $this->storage->files('box');
        sort($files);
        self::assertSame(['box/a.txt', 'box/b.txt'], $files);

        $all = $this->storage->allFiles('box');
        sort($all);
        self::assertSame(['box/a.txt', 'box/b.txt', 'box/sub/c.txt'], $all);
    }

    public function test_url_uses_resolver_or_returns_null(): void
    {
        self::assertNull($this->storage->url('a.txt'));

        $resolved = new FlysystemStorage(
            new Filesystem(new LocalFilesystemAdapter($this->root)),
            static fn (string $path): string => 'https://cdn.test/'.$path,
        );
        self::assertSame('https://cdn.test/a.txt', $resolved->url('a.txt'));
    }
}
