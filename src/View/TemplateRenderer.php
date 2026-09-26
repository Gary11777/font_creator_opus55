<?php

declare(strict_types=1);

namespace FontCreator\View;

/**
 * Renders PHP-syntax `.tpl` templates. Templates receive their variables
 * plus an `$e()` helper for HTML escaping.
 */
final readonly class TemplateRenderer
{
    public function __construct(private string $templateDirectory)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        if (preg_match('/\A[\w-]+\z/', $template) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid template name "%s".', $template));
        }

        $file = $this->templateDirectory . DIRECTORY_SEPARATOR . $template . '.tpl';
        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('Template "%s" not found.', $template));
        }

        $data['e'] = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };

        ob_start();
        try {
            $render($file, $data);

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
