<?php

declare(strict_types=1);

namespace FontCreator\Tests\Model;

use FontCreator\Model\Exception\InvalidMatrixException;
use FontCreator\Model\Matrix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MatrixTest extends TestCase
{
    public function testEmptyMatrixIsAllZeros(): void
    {
        $matrix = Matrix::empty();

        self::assertTrue($matrix->isEmpty());
        self::assertSame(str_repeat("00000000\n", 8), $matrix->toText());
    }

    public function testSecondCellOfFirstRowProducesSpecExample(): void
    {
        $matrix = Matrix::empty()->toggle(0, 1);

        self::assertSame(
            "01000000\n00000000\n00000000\n00000000\n00000000\n00000000\n00000000\n00000000\n",
            $matrix->toText(),
        );
    }

    public function testToggleIsImmutableAndReversible(): void
    {
        $original = Matrix::empty();
        $toggled = $original->toggle(3, 4);

        self::assertFalse($original->isSet(3, 4));
        self::assertTrue($toggled->isSet(3, 4));
        self::assertTrue($toggled->toggle(3, 4)->equals($original));
    }

    public function testFromArrayAcceptsIntegerBooleanAndStringRows(): void
    {
        $rows = array_fill(0, 8, [0, 0, 0, 0, 0, 0, 0, 0]);
        $rows[0] = [1, 0, 0, 0, 0, 0, 0, 1];
        $rows[1] = [true, false, false, false, false, false, false, false];
        $rows[2] = '00011000';

        $matrix = Matrix::fromArray($rows);

        self::assertSame([1, 0, 0, 0, 0, 0, 0, 1], $matrix->toArray()[0]);
        self::assertSame([1, 0, 0, 0, 0, 0, 0, 0], $matrix->toArray()[1]);
        self::assertSame([0, 0, 0, 1, 1, 0, 0, 0], $matrix->toArray()[2]);
    }

    public function testTextRoundTrip(): void
    {
        $matrix = Matrix::empty()->toggle(0, 0)->toggle(7, 7)->toggle(4, 2);

        self::assertTrue(Matrix::fromText($matrix->toText())->equals($matrix));
    }

    public function testFromTextToleratesWindowsLineEndingsAndMissingTrailingNewline(): void
    {
        $text = "10000000\r\n" . str_repeat("00000000\r\n", 6) . '00000001';

        $matrix = Matrix::fromText($text);

        self::assertTrue($matrix->isSet(0, 0));
        self::assertTrue($matrix->isSet(7, 7));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidMatrices(): iterable
    {
        $valid = array_fill(0, 8, array_fill(0, 8, 0));

        yield 'not an array' => ['01000000'];
        yield 'null' => [null];
        yield 'too few rows' => [array_slice($valid, 0, 7)];
        yield 'too many rows' => [[...$valid, $valid[0]]];
        yield 'associative rows' => [array_combine(range(1, 8), $valid)];
        yield 'short row' => [array_replace($valid, [3 => [0, 0, 0]])];
        yield 'long string row' => [array_replace($valid, [3 => '000000000'])];
        yield 'value 2' => [array_replace($valid, [0 => [2, 0, 0, 0, 0, 0, 0, 0]])];
        yield 'float value' => [array_replace($valid, [0 => [1.0, 0, 0, 0, 0, 0, 0, 0]])];
        yield 'letter in string row' => [array_replace($valid, [0 => '0000000x'])];
        yield 'nested array cell' => [array_replace($valid, [0 => [[1], 0, 0, 0, 0, 0, 0, 0]])];
    }

    #[DataProvider('invalidMatrices')]
    public function testFromArrayRejectsInvalidInput(mixed $rows): void
    {
        $this->expectException(InvalidMatrixException::class);

        Matrix::fromArray($rows);
    }

    public function testOutOfBoundsAccessIsRejected(): void
    {
        $this->expectException(InvalidMatrixException::class);
        $this->expectExceptionMessage('Cell (8, 0) is outside the 8x8 matrix.');

        Matrix::empty()->toggle(8, 0);
    }
}
