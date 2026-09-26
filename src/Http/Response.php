<?php

declare(strict_types=1);

namespace FontCreator\Http;

final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
    }

    public static function download(string $contents, string $filename): self
    {
        return new self($contents, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', addcslashes($filename, '"\\')),
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->body, $this->status, [...$this->headers, ...$headers]);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('X-Content-Type-Options: nosniff');
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
