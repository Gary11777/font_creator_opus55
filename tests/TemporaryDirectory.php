<?php

declare(strict_types=1);

namespace FontCreator\Tests;

trait TemporaryDirectory
{
    private string $tmpDir;

    protected function createTemporaryDirectory(): string
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'font-creator-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir);

        return $this->tmpDir;
    }

    protected function removeTemporaryDirectory(): void
    {
        if (!isset($this->tmpDir) || !is_dir($this->tmpDir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            @chmod($item->getPathname(), 0777);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        @chmod($this->tmpDir, 0777);
        rmdir($this->tmpDir);
    }
}
