<?php

declare(strict_types=1);

use FontCreator\Application;
use FontCreator\Http\Request;

// Built-in dev server: let it serve static assets (css, js) directly.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

$root = dirname(__DIR__);

$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'FontCreator\\';
        if (str_starts_with($class, $prefix)) {
            $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

Application::create(
    outputDirectory: getenv('FONT_CREATOR_OUTPUT_DIR') ?: $root . '/output',
    templateDirectory: $root . '/templates',
)->handle(Request::fromGlobals())->send();
