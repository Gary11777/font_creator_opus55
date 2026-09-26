<?php

declare(strict_types=1);

namespace FontCreator\Tests\Storage;

use FontCreator\Storage\FileWriter;
use FontCreator\Storage\StorageException;
use FontCreator\Tests\TemporaryDirectory;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class FileWriterTest extends TestCase
{
    use TemporaryDirectory;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = $this->createTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testWritesAndOverwritesFile(): void
    {
        $writer = new FileWriter($this->dir);

        $path = $writer->write('output.txt', 'first');
        $writer->write('output.txt', 'second');

        self::assertSame($this->dir . DIRECTORY_SEPARATOR . 'output.txt', $path);
        self::assertSame('second', file_get_contents($path));
        self::assertSame('second', $writer->read('output.txt'));
    }

    public function testLeavesNoTemporaryFilesBehind(): void
    {
        $writer = new FileWriter($this->dir);
        $writer->write('output.txt', 'data');
        $writer->write('output.txt', 'data again');

        self::assertSame(['output.txt'], array_values(array_diff(scandir($this->dir), ['.', '..'])));
    }

    public function testCreatesMissingDirectory(): void
    {
        $nested = $this->dir . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b';
        $writer = new FileWriter($nested);

        $writer->write('output.txt', 'x');

        self::assertFileExists($nested . DIRECTORY_SEPARATOR . 'output.txt');
    }

    public function testFailsClearlyWhenDirectoryCannotBeCreated(): void
    {
        $blocker = $this->dir . DIRECTORY_SEPARATOR . 'not-a-dir';
        file_put_contents($blocker, '');
        $writer = new FileWriter($blocker . DIRECTORY_SEPARATOR . 'output');

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('could not be created');

        $writer->write('output.txt', 'x');
    }

    public function testFailsClearlyWhenDirectoryIsNotWritable(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX directory permissions are not enforced on Windows.');
        }
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('root ignores directory permissions.');
        }

        chmod($this->dir, 0555);
        $writer = new FileWriter($this->dir);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('is not writable');

        $writer->write('output.txt', 'x');
    }

    public function testReadReturnsNullForMissingFile(): void
    {
        self::assertNull(new FileWriter($this->dir)->read('missing.txt'));
    }

    public function testListReturnsNaturallySortedMatches(): void
    {
        $writer = new FileWriter($this->dir);
        foreach (['output_10.txt', 'output_2.txt', 'output.txt', 'notes.md'] as $name) {
            $writer->write($name, '');
        }

        self::assertSame(['output_2.txt', 'output_10.txt'], $writer->list('output_*.txt'));
    }

    public function testListOnMissingDirectoryIsEmpty(): void
    {
        self::assertSame([], new FileWriter($this->dir . DIRECTORY_SEPARATOR . 'nope')->list());
    }

    public function testDelete(): void
    {
        $writer = new FileWriter($this->dir);
        $writer->write('output_1.txt', 'x');

        self::assertTrue($writer->delete('output_1.txt'));
        self::assertFalse($writer->delete('output_1.txt'));
        self::assertFalse($writer->exists('output_1.txt'));
    }

    #[TestWith(['../escape.txt'])]
    #[TestWith(['sub/file.txt'])]
    #[TestWith(['.hidden'])]
    #[TestWith([''])]
    public function testRejectsFileNamesOutsideDirectory(string $filename): void
    {
        $this->expectException(StorageException::class);

        new FileWriter($this->dir)->write($filename, 'x');
    }
}
