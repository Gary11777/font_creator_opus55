<?php

declare(strict_types=1);

namespace FontCreator\Controller;

use FontCreator\Http\Request;
use FontCreator\Http\Response;
use FontCreator\Model\Matrix;
use FontCreator\Storage\MatrixRepository;

/**
 * The working matrix, persisted as output/output.txt on every change.
 */
final readonly class MatrixController
{
    public function __construct(private MatrixRepository $repository)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        return Response::json($this->present($this->repository->loadCurrent()));
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, array $params): Response
    {
        $matrix = Matrix::fromArray($request->json()['matrix'] ?? null);
        $this->repository->saveCurrent($matrix);

        return Response::json($this->present($matrix));
    }

    /**
     * @param array<string, string> $params
     */
    public function download(Request $request, array $params): Response
    {
        return Response::download($this->repository->loadCurrent()->toText(), MatrixRepository::CURRENT_FILE);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Matrix $matrix): array
    {
        return [
            'file' => MatrixRepository::CURRENT_FILE,
            'matrix' => $matrix->toArray(),
            'text' => $matrix->toText(),
        ];
    }
}
