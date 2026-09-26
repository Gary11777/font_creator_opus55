<?php

declare(strict_types=1);

namespace FontCreator\Tests;

use FontCreator\Application;
use FontCreator\Http\Request;
use FontCreator\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Drives the full stack (routing, controllers, storage, templates) through
 * Request objects, without a web server.
 */
final class ApplicationTest extends TestCase
{
    use TemporaryDirectory;

    private string $dir;
    private Application $app;

    protected function setUp(): void
    {
        $this->dir = $this->createTemporaryDirectory();
        $this->app = Application::create($this->dir, dirname(__DIR__) . '/templates');
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testClickingSecondCellOfFirstRowWritesSpecExample(): void
    {
        $rows = array_fill(0, 8, array_fill(0, 8, 0));
        $rows[0][1] = 1;

        $response = $this->postJson('/api/matrix', ['matrix' => $rows]);

        self::assertSame(200, $response->status);
        self::assertStringEqualsFile(
            $this->dir . '/output.txt',
            "01000000\n00000000\n00000000\n00000000\n00000000\n00000000\n00000000\n00000000\n",
        );
        self::assertSame($rows, $this->decode($response)['matrix']);
    }

    public function testEachSaveOverwritesOutputTxt(): void
    {
        $rows = array_fill(0, 8, array_fill(0, 8, 0));
        $rows[0][0] = 1;
        $this->postJson('/api/matrix', ['matrix' => $rows]);
        $rows[0][0] = 0;
        $rows[7][7] = 1;
        $this->postJson('/api/matrix', ['matrix' => $rows]);

        self::assertStringEqualsFile($this->dir . '/output.txt', str_repeat("00000000\n", 7) . "00000001\n");
    }

    public function testInvalidMatrixIsRejectedWithoutWriting(): void
    {
        $response = $this->postJson('/api/matrix', ['matrix' => [[1, 0]]]);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('error', $this->decode($response));
        self::assertFileDoesNotExist($this->dir . '/output.txt');
    }

    public function testMalformedJsonIsRejected(): void
    {
        $response = $this->app->handle(new Request('POST', '/api/matrix', ['content-type' => 'application/json'], '{nope'));

        self::assertSame(400, $response->status);
    }

    public function testNonJsonContentTypeIsRejected(): void
    {
        $response = $this->app->handle(new Request('POST', '/api/matrix', ['content-type' => 'text/plain'], '{}'));

        self::assertSame(415, $response->status);
    }

    public function testUnwritableOutputReturnsServerErrorWithMessage(): void
    {
        $blocker = $this->dir . '/file';
        file_put_contents($blocker, '');
        $app = Application::create($blocker . '/output', dirname(__DIR__) . '/templates');
        $previousLog = ini_set('error_log', $this->dir . '/php-errors.log');

        try {
            $response = $app->handle(new Request(
                'POST',
                '/api/matrix',
                ['content-type' => 'application/json'],
                json_encode(['matrix' => array_fill(0, 8, '00000000')]),
            ));
        } finally {
            ini_set('error_log', (string) $previousLog);
        }

        self::assertSame(500, $response->status);
        self::assertStringContainsString('could not be created', (string) file_get_contents($this->dir . '/php-errors.log'));
        self::assertStringContainsString('could not be created', $this->decode($response)['error']);
    }

    public function testIndexRendersMatrixWithStoredState(): void
    {
        file_put_contents($this->dir . '/output.txt', "01000000\n" . str_repeat("00000000\n", 7));

        $response = $this->app->handle(new Request('GET', '/'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<table class="matrix8"', $response->body);
        self::assertSame(64, substr_count($response->body, '<td '));
        self::assertSame(1, substr_count($response->body, 'class="is-on"'));
        self::assertStringContainsString('data-row="0" data-col="1"', $response->body);
    }

    public function testDownloadServesOutputTxtAsAttachment(): void
    {
        file_put_contents($this->dir . '/output.txt', "10000000\n" . str_repeat("00000000\n", 7));

        $response = $this->app->handle(new Request('GET', '/download'));

        self::assertSame(200, $response->status);
        self::assertSame('attachment; filename="output.txt"', $response->headers['Content-Disposition']);
        self::assertStringStartsWith("10000000\n", $response->body);
    }

    public function testGlyphCrudFlow(): void
    {
        $rows = array_fill(0, 8, '00000000');
        $rows[3] = '11111111';

        $created = $this->postJson('/api/glyphs', ['label' => 'dash', 'matrix' => $rows]);
        self::assertSame(201, $created->status);
        self::assertFileExists($this->dir . '/output_dash.txt');

        self::assertSame(200, $this->postJson('/api/glyphs', ['label' => 'dash', 'matrix' => $rows])->status);

        self::assertSame(['dash'], $this->decode($this->app->handle(new Request('GET', '/api/glyphs')))['glyphs']);

        $shown = $this->decode($this->app->handle(new Request('GET', '/api/glyphs/dash')));
        self::assertSame([1, 1, 1, 1, 1, 1, 1, 1], $shown['matrix'][3]);

        $download = $this->app->handle(new Request('GET', '/download/dash'));
        self::assertSame('attachment; filename="output_dash.txt"', $download->headers['Content-Disposition']);

        self::assertSame(204, $this->app->handle(new Request('DELETE', '/api/glyphs/dash'))->status);
        self::assertSame(404, $this->app->handle(new Request('GET', '/api/glyphs/dash'))->status);
        self::assertSame(404, $this->app->handle(new Request('DELETE', '/api/glyphs/dash'))->status);
    }

    public function testInvalidGlyphLabelIsRejected(): void
    {
        $response = $this->postJson('/api/glyphs', ['label' => '../hack', 'matrix' => array_fill(0, 8, '00000000')]);

        self::assertSame(422, $response->status);
        self::assertSame([], array_values(array_diff(scandir($this->dir), ['.', '..'])));
    }

    public function testUnknownRouteAndWrongMethod(): void
    {
        self::assertSame(404, $this->app->handle(new Request('GET', '/nope'))->status);

        $response = $this->app->handle(new Request('PUT', '/api/matrix'));
        self::assertSame(405, $response->status);
        self::assertSame('GET, POST', $response->headers['Allow']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function postJson(string $path, array $data): Response
    {
        return $this->app->handle(new Request(
            'POST',
            $path,
            ['content-type' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
    }
}
