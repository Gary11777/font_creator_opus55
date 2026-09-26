<?php

declare(strict_types=1);

namespace FontCreator\Storage;

use FontCreator\Model\Exception\InvalidMatrixException;
use FontCreator\Model\Glyph;
use FontCreator\Model\GlyphLabel;
use FontCreator\Model\Matrix;

/**
 * Persists the working matrix as output.txt and labelled glyphs as
 * output_<label>.txt.
 */
final readonly class MatrixRepository
{
    public const string CURRENT_FILE = 'output.txt';
    private const string GLYPH_PREFIX = 'output_';
    private const string EXTENSION = '.txt';

    public function __construct(private FileWriter $files)
    {
    }

    public function saveCurrent(Matrix $matrix): void
    {
        $this->files->write(self::CURRENT_FILE, $matrix->toText());
    }

    /**
     * Returns an empty matrix when nothing is stored yet or the file was
     * edited into an unreadable state.
     */
    public function loadCurrent(): Matrix
    {
        return $this->parse($this->files->read(self::CURRENT_FILE)) ?? Matrix::empty();
    }

    public function saveGlyph(Glyph $glyph): void
    {
        $this->files->write(self::glyphFile($glyph->label), $glyph->matrix->toText());
    }

    public function findGlyph(GlyphLabel $label): ?Glyph
    {
        $matrix = $this->parse($this->files->read(self::glyphFile($label)));

        return $matrix === null ? null : new Glyph($label, $matrix);
    }

    public function deleteGlyph(GlyphLabel $label): bool
    {
        return $this->files->delete(self::glyphFile($label));
    }

    /**
     * @return list<GlyphLabel>
     */
    public function listGlyphLabels(): array
    {
        $labels = [];
        foreach ($this->files->list(self::GLYPH_PREFIX . '*' . self::EXTENSION) as $name) {
            $value = substr($name, strlen(self::GLYPH_PREFIX), -strlen(self::EXTENSION));
            if (preg_match('/\A' . GlyphLabel::PATTERN . '\z/', $value) === 1) {
                $labels[] = new GlyphLabel($value);
            }
        }

        return $labels;
    }

    public function currentFilePath(): string
    {
        return $this->files->path(self::CURRENT_FILE);
    }

    public function glyphFilePath(GlyphLabel $label): string
    {
        return $this->files->path(self::glyphFile($label));
    }

    public static function glyphFile(GlyphLabel $label): string
    {
        return self::GLYPH_PREFIX . $label->value . self::EXTENSION;
    }

    private function parse(?string $contents): ?Matrix
    {
        if ($contents === null) {
            return null;
        }

        try {
            return Matrix::fromText($contents);
        } catch (InvalidMatrixException) {
            return null;
        }
    }
}
