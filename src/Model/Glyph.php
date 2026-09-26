<?php

declare(strict_types=1);

namespace FontCreator\Model;

final readonly class Glyph
{
    public function __construct(
        public GlyphLabel $label,
        public Matrix $matrix,
    ) {
    }
}
