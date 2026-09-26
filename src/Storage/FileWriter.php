<?php

declare(strict_types=1);

namespace FontCreator\Storage;

/**
 * Reads and writes plain files inside a single directory. File names are
 * restricted to bare names so callers can never escape that directory.
 */
final readonly class FileWriter
{
    public function __construct(
        private string $directory,
        private int $directoryMode = 0775,
    ) {
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Atomically replaces the file: readers see either the old or the new
     * contents, never a partially written file.
     */
    public function write(string $filename, string $contents): string
    {
        $this->ensureWritableDirectory();
        $path = $this->path($filename);

        $tmp = @tempnam($this->directory, '.tmp_');
        if ($tmp === false) {
            throw new StorageException(sprintf('Could not create a temporary file in "%s".', $this->directory));
        }

        try {
            if (@file_put_contents($tmp, $contents) !== strlen($contents)) {
                throw new StorageException(sprintf('Could not write "%s": %s', $filename, self::lastError()));
            }
            @chmod($tmp, 0664);

            if (!@rename($tmp, $path)) {
                // On Windows rename() fails if another process holds the target open.
                if (@file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
                    throw new StorageException(sprintf('Could not replace "%s": %s', $filename, self::lastError()));
                }
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return $path;
    }

    public function read(string $filename): ?string
    {
        $path = $this->path($filename);
        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new StorageException(sprintf('Could not read "%s": %s', $filename, self::lastError()));
        }

        return $contents;
    }

    public function exists(string $filename): bool
    {
        return is_file($this->path($filename));
    }

    public function delete(string $filename): bool
    {
        $path = $this->path($filename);
        if (!is_file($path)) {
            return false;
        }
        if (!@unlink($path)) {
            throw new StorageException(sprintf('Could not delete "%s": %s', $filename, self::lastError()));
        }

        return true;
    }

    /**
     * @return list<string> bare file names matching the glob pattern, sorted
     */
    public function list(string $pattern = '*'): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = array_filter(glob($this->directory . DIRECTORY_SEPARATOR . $pattern) ?: [], is_file(...));
        $names = array_map(basename(...), $files);
        natcasesort($names);

        return array_values($names);
    }

    public function path(string $filename): string
    {
        if ($filename === '' || $filename !== basename($filename) || str_starts_with($filename, '.')) {
            throw new StorageException(sprintf('Invalid file name "%s".', $filename));
        }

        return $this->directory . DIRECTORY_SEPARATOR . $filename;
    }

    private function ensureWritableDirectory(): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, $this->directoryMode, true) && !is_dir($this->directory)) {
            throw new StorageException(sprintf('Output directory "%s" does not exist and could not be created.', $this->directory));
        }

        if (!is_writable($this->directory)) {
            throw new StorageException(sprintf('Output directory "%s" is not writable.', $this->directory));
        }
    }

    private static function lastError(): string
    {
        return error_get_last()['message'] ?? 'unknown error';
    }
}
