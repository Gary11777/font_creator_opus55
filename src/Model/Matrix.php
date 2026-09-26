<?php

declare(strict_types=1);

namespace FontCreator\Model;

use FontCreator\Model\Exception\InvalidMatrixException;

/**
 * Immutable 8x8 grid of pixels. `true` means the pixel is set (black).
 */
final readonly class Matrix
{
    public const int SIZE = 8;

    /**
     * @param list<list<bool>> $cells
     */
    private function __construct(private array $cells)
    {
    }

    public static function empty(): self
    {
        return new self(array_fill(0, self::SIZE, array_fill(0, self::SIZE, false)));
    }

    /**
     * Builds a matrix from decoded JSON. Each row may be either a list of
     * 0/1 (or booleans) or a string such as "01000000".
     */
    public static function fromArray(mixed $rows): self
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== self::SIZE) {
            throw new InvalidMatrixException(sprintf('Matrix must contain exactly %d rows.', self::SIZE));
        }

        $cells = [];
        foreach ($rows as $r => $row) {
            if (is_string($row)) {
                $row = str_split($row);
            }
            if (!is_array($row) || !array_is_list($row) || count($row) !== self::SIZE) {
                throw new InvalidMatrixException(sprintf('Row %d must contain exactly %d cells.', $r + 1, self::SIZE));
            }
            foreach ($row as $c => $value) {
                $cells[$r][$c] = match ($value) {
                    1, '1', true => true,
                    0, '0', false => false,
                    default => throw new InvalidMatrixException(
                        sprintf('Cell at row %d, column %d must be 0 or 1.', $r + 1, $c + 1),
                    ),
                };
            }
        }

        return new self($cells);
    }

    /**
     * Parses the on-disk format produced by {@see toText()}.
     */
    public static function fromText(string $text): self
    {
        $lines = preg_split('/\R/', trim($text));

        return self::fromArray($lines === false ? [] : $lines);
    }

    public function isSet(int $row, int $col): bool
    {
        $this->assertInBounds($row, $col);

        return $this->cells[$row][$col];
    }

    public function withCell(int $row, int $col, bool $value): self
    {
        $this->assertInBounds($row, $col);
        $cells = $this->cells;
        $cells[$row][$col] = $value;

        return new self($cells);
    }

    public function toggle(int $row, int $col): self
    {
        return $this->withCell($row, $col, !$this->isSet($row, $col));
    }

    public function isEmpty(): bool
    {
        foreach ($this->cells as $row) {
            if (in_array(true, $row, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<list<int>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (array $row): array => array_map(static fn (bool $cell): int => (int) $cell, $row),
            $this->cells,
        );
    }

    /**
     * One line per row, "1" for a set pixel and "0" otherwise.
     */
    public function toText(): string
    {
        $lines = array_map(static fn (array $row): string => implode('', $row), $this->toArray());

        return implode("\n", $lines) . "\n";
    }

    public function equals(self $other): bool
    {
        return $this->cells === $other->cells;
    }

    private function assertInBounds(int $row, int $col): void
    {
        if ($row < 0 || $row >= self::SIZE || $col < 0 || $col >= self::SIZE) {
            throw new InvalidMatrixException(sprintf('Cell (%1$d, %2$d) is outside the %3$dx%3$d matrix.', $row, $col, self::SIZE));
        }
    }
}
