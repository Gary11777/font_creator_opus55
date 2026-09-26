<?php

declare(strict_types=1);

namespace FontCreator\Model;

use FontCreator\Model\Exception\InvalidLabelException;

/**
 * Name of a saved character. Restricted to a filename-safe alphabet because
 * it becomes part of the file name (output_<label>.txt).
 */
final readonly class GlyphLabel implements \Stringable
{
    public const string PATTERN = '[A-Za-z0-9_-]{1,32}';

    public function __construct(public string $value)
    {
        if (preg_match('/\A' . self::PATTERN . '\z/', $value) !== 1) {
            throw new InvalidLabelException(
                'Label must be 1-32 characters long and contain only letters, digits, "_" or "-".',
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
