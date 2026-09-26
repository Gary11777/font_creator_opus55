<?php

declare(strict_types=1);

namespace FontCreator;

use FontCreator\Controller\GlyphController;
use FontCreator\Controller\MatrixController;
use FontCreator\Controller\PageController;
use FontCreator\Http\HttpException;
use FontCreator\Http\Request;
use FontCreator\Http\Response;
use FontCreator\Http\Router;
use FontCreator\Model\Exception\InvalidLabelException;
use FontCreator\Model\Exception\InvalidMatrixException;
use FontCreator\Storage\FileWriter;
use FontCreator\Storage\MatrixRepository;
use FontCreator\Storage\StorageException;
use FontCreator\View\TemplateRenderer;

final readonly class Application
{
    public function __construct(private Router $router)
    {
    }

    public static function create(string $outputDirectory, string $templateDirectory): self
    {
        $repository = new MatrixRepository(new FileWriter($outputDirectory));
        $pages = new PageController(new TemplateRenderer($templateDirectory), $repository);
        $matrix = new MatrixController($repository);
        $glyphs = new GlyphController($repository);

        $label = '{label:[A-Za-z0-9_-]+}';

        $router = new Router()
            ->get('/', $pages->index(...))
            ->get('/api/matrix', $matrix->show(...))
            ->post('/api/matrix', $matrix->save(...))
            ->get('/download', $matrix->download(...))
            ->get('/api/glyphs', $glyphs->index(...))
            ->post('/api/glyphs', $glyphs->store(...))
            ->get('/api/glyphs/' . $label, $glyphs->show(...))
            ->delete('/api/glyphs/' . $label, $glyphs->destroy(...))
            ->get('/download/' . $label, $glyphs->download(...));

        return new self($router);
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return $this->error($request, $e->status, $e->getMessage())->withHeaders($e->headers);
        } catch (InvalidMatrixException | InvalidLabelException $e) {
            return $this->error($request, 422, $e->getMessage());
        } catch (StorageException $e) {
            error_log('[font-creator] ' . $e->getMessage());

            return $this->error($request, 500, 'Could not save the file: ' . $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[font-creator] ' . $e);

            return $this->error($request, 500, 'Internal server error.');
        }
    }

    private function error(Request $request, int $status, string $message): Response
    {
        if (str_starts_with($request->path, '/api/')) {
            return Response::json(['error' => $message], $status);
        }

        return new Response($message, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
