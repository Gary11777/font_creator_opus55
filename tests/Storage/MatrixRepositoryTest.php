<?php

declare(strict_types=1);

namespace FontCreator\Tests\Storage;

use FontCreator\Model\Glyph;
use FontCreator\Model\GlyphLabel;
use FontCreator\Model\Matrix;
use FontCreator\Storage\FileWriter;
use FontCreator\Storage\MatrixRepository;
use FontCreator\Tests\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class MatrixRepositoryTest extends TestCase
{
    use TemporaryDirectory;

    private string $dir;
    private MatrixRepository $repository;

    protected function setUp(): void
    {
        $this->dir = $this->createTemporaryDirectory();
        $this->repository = new MatrixRepository(new FileWriter($this->dir));
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testSavesCurrentMatrixToOutputTxt(): void
    {
        $this->repository->saveCurrent(Matrix::empty()->toggle(0, 1));

        self::assertStringEqualsFile(
            $this->dir . '/output.txt',
            "01000000\n00000000\n00000000\n00000000\n00000000\n00000000\n00000000\n00000000\n",
        );
    }

    public function testLoadCurrentRoundTrips(): void
    {
        $matrix = Matrix::empty()->toggle(2, 5);
        $this->repository->saveCurrent($matrix);

        self::assertTrue($this->repository->loadCurrent()->equals($matrix));
    }

    public function testLoadCurrentFallsBackToEmptyMatrix(): void
    {
        self::assertTrue($this->repository->loadCurrent()->isEmpty());

        file_put_contents($this->dir . '/output.txt', "garbage\n");
        self::assertTrue($this->repository->loadCurrent()->isEmpty());
    }

    public function testGlyphLifecycle(): void
    {
        $label = new GlyphLabel('A');
        $matrix = Matrix::empty()->toggle(0, 3)->toggle(0, 4);

        $this->repository->saveGlyph(new Glyph($label, $matrix));

        self::assertFileExists($this->dir . '/output_A.txt');
        self::assertTrue($this->repository->findGlyph($label)?->matrix->equals($matrix));
        self::assertTrue($this->repository->deleteGlyph($label));
        self::assertNull($this->repository->findGlyph($label));
    }

    public function testGlyphsDoNotAffectCurrentMatrix(): void
    {
        $this->repository->saveCurrent(Matrix::empty()->toggle(1, 1));
        $this->repository->saveGlyph(new Glyph(new GlyphLabel('x'), Matrix::empty()->toggle(7, 7)));

        self::assertTrue($this->repository->loadCurrent()->isSet(1, 1));
        self::assertFalse($this->repository->loadCurrent()->isSet(7, 7));
    }

    public function testListsGlyphLabelsAndIgnoresUnrelatedFiles(): void
    {
        foreach (['2', '10', 'B'] as $label) {
            $this->repository->saveGlyph(new Glyph(new GlyphLabel($label), Matrix::empty()));
        }
        $this->repository->saveCurrent(Matrix::empty());
        file_put_contents($this->dir . '/output_bad name.txt', '');
        file_put_contents($this->dir . '/readme.txt', '');

        $labels = array_map(static fn (GlyphLabel $l): string => $l->value, $this->repository->listGlyphLabels());

        self::assertSame(['2', '10', 'B'], $labels);
    }
}
