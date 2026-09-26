<?php

declare(strict_types=1);

namespace FontCreator\Tests\Model;

use FontCreator\Model\Exception\InvalidLabelException;
use FontCreator\Model\GlyphLabel;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class GlyphLabelTest extends TestCase
{
    #[TestWith(['A'])]
    #[TestWith(['1'])]
    #[TestWith(['letter_a-lower'])]
    #[TestWith(['abcdefghijklmnopqrstuvwxyz012345'])]
    public function testAcceptsFilenameSafeLabels(string $value): void
    {
        self::assertSame($value, (string) new GlyphLabel($value));
    }

    #[TestWith([''])]
    #[TestWith(['../secret'])]
    #[TestWith(['a/b'])]
    #[TestWith(['a b'])]
    #[TestWith(['a.txt'])]
    #[TestWith(["a\n"])]
    #[TestWith(['ä'])]
    #[TestWith(['abcdefghijklmnopqrstuvwxyz0123456'])]
    public function testRejectsUnsafeLabels(string $value): void
    {
        $this->expectException(InvalidLabelException::class);

        new GlyphLabel($value);
    }
}
