<?php

declare(strict_types=1);

namespace FontCreator\Http;

final class Router
{
    /** @var list<array{method: string, regex: string, handler: \Closure(Request, array<string, string>): Response}> */
    private array $routes = [];

    /**
     * Registers a route. Path parameters use the `{name:regex}` syntax,
     * e.g. `/api/glyphs/{label:[A-Za-z0-9_-]+}`; the regex cannot contain braces.
     *
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function add(string $method, string $pattern, callable $handler): self
    {
        $regex = preg_replace_callback(
            '/\{(\w+):([^}]+)\}/',
            static fn (array $m): string => sprintf('(?P<%s>%s)', $m[1], $m[2]),
            $pattern,
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#\A' . $regex . '\z#',
            'handler' => $handler(...),
        ];

        return $this;
    }

    public function get(string $pattern, callable $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): self
    {
        return $this->add('DELETE', $pattern, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            $method = $request->method === 'HEAD' ? 'GET' : $request->method;
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request, $params);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed.', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404, 'Not found.');
    }
}
