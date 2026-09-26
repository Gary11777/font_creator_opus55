<?php

declare(strict_types=1);

namespace FontCreator\Controller;

use FontCreator\Http\Request;
use FontCreator\Http\Response;
use FontCreator\Model\GlyphLabel;
use FontCreator\Model\Matrix;
use FontCreator\Storage\MatrixRepository;
use FontCreator\View\TemplateRenderer;

final readonly class PageController
{
    public function __construct(
        private TemplateRenderer $view,
        private MatrixRepository $repository,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): Response
    {
        return Response::html($this->view->render('main', [
            'size' => Matrix::SIZE,
            'matrix' => $this->repository->loadCurrent(),
            'glyphs' => array_map(static fn (GlyphLabel $l): string => $l->value, $this->repository->listGlyphLabels()),
        ]));
    }
}
