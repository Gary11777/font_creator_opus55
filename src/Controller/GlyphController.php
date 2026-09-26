<?php

declare(strict_types=1);

namespace FontCreator\Controller;

use FontCreator\Http\HttpException;
use FontCreator\Http\Request;
use FontCreator\Http\Response;
use FontCreator\Model\Glyph;
use FontCreator\Model\GlyphLabel;
use FontCreator\Model\Matrix;
use FontCreator\Storage\MatrixRepository;

/**
 * Labelled characters, each persisted as output/output_<label>.txt.
 */
final readonly class GlyphController
{
    public function __construct(private MatrixRepository $repository)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): Response
    {
        return Response::json([
            'glyphs' => array_map(static fn (GlyphLabel $l): string => $l->value, $this->repository->listGlyphLabels()),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): Response
    {
        $data = $request->json();
        $label = new GlyphLabel(is_string($data['label'] ?? null) ? trim($data['label']) : '');
        $glyph = new Glyph($label, Matrix::fromArray($data['matrix'] ?? null));

        $created = $this->repository->findGlyph($label) === null;
        $this->repository->saveGlyph($glyph);

        return Response::json($this->present($glyph), $created ? 201 : 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        return Response::json($this->present($this->find($params['label'])));
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params): Response
    {
        if (!$this->repository->deleteGlyph(new GlyphLabel($params['label']))) {
            throw new HttpException(404, sprintf('Glyph "%s" not found.', $params['label']));
        }

        return Response::noContent();
    }

    /**
     * @param array<string, string> $params
     */
    public function download(Request $request, array $params): Response
    {
        $glyph = $this->find($params['label']);

        return Response::download($glyph->matrix->toText(), MatrixRepository::glyphFile($glyph->label));
    }

    private function find(string $label): Glyph
    {
        return $this->repository->findGlyph(new GlyphLabel($label))
            ?? throw new HttpException(404, sprintf('Glyph "%s" not found.', $label));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Glyph $glyph): array
    {
        return [
            'label' => $glyph->label->value,
            'file' => MatrixRepository::glyphFile($glyph->label),
            'matrix' => $glyph->matrix->toArray(),
            'text' => $glyph->matrix->toText(),
        ];
    }
}
