<?php

declare(strict_types=1);

namespace FontCreator\Http;

final readonly class Request
{
    private const int MAX_BODY_BYTES = 16_384;

    /**
     * @param array<string, string> $headers lower-cased header names
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers = [],
        public string $body = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? rawurldecode($path) : '/',
            $headers,
            (string) file_get_contents('php://input', length: self::MAX_BODY_BYTES + 1),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Decodes a JSON object body. Requiring the JSON content type also means
     * a cross-site HTML form cannot submit to the API without a CORS preflight.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $type = strtolower(trim(explode(';', $this->header('content-type') ?? '')[0]));
        if ($type !== 'application/json') {
            throw new HttpException(415, 'Request body must be sent as application/json.');
        }
        if (strlen($this->body) > self::MAX_BODY_BYTES) {
            throw new HttpException(413, 'Request body is too large.');
        }

        try {
            $data = json_decode($this->body, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new HttpException(400, 'Malformed JSON: ' . $e->getMessage());
        }

        if (!is_array($data) || array_is_list($data) && $data !== []) {
            throw new HttpException(400, 'JSON body must be an object.');
        }

        return $data;
    }
}
