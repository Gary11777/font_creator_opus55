# Font Creator

A small web-based editor for 8×8 pixel characters. Click cells in the matrix to
toggle them; every change is sent to the PHP backend, which writes
`output/output.txt` — 8 lines of 8 characters, `1` for a black cell and `0`
for an empty one:

```
01000000
00000000
00000000
00000000
00000000
00000000
00000000
00000000
```

Drawings can also be saved under a label (`output/output_<label>.txt`), loaded
back, downloaded and deleted.

## Requirements

- PHP 8.4
- Composer (only needed for the test suite; the app itself runs without `vendor/`)
- Optionally Docker, to run behind nginx + PHP-FPM

## Running

**Built-in development server**

```bash
composer install
composer serve        # php -S localhost:8000 -t public public/index.php
```

Open <http://localhost:8000>.

**nginx + PHP-FPM (Docker)**

```bash
docker compose up
```

Open <http://localhost:8080>. The `php` service is the stock `php:8.4-fpm`
image; `docker/nginx.conf` routes every non-static request to
`public/index.php` over FastCGI. The same nginx config works for a
non-Docker PHP-FPM install once `fastcgi_pass` points at your FPM socket.

The output directory defaults to `./output` and can be changed with the
`FONT_CREATOR_OUTPUT_DIR` environment variable. It must be writable by the
PHP process user (e.g. `www-data` under FPM); it is created if missing, and a
clear error is returned to the UI if it cannot be created or written.

## Tests

```bash
composer test
```

## Structure

```
public/            web root: front controller, css/, js/
templates/main.tpl page template (the table.matrix8 matrix)
src/
  Application.php  wiring, routes, exception → HTTP status mapping
  Controller/      PageController, MatrixController, GlyphController
  Http/            Request, Response, Router, HttpException
  Model/           Matrix (8×8 value object), Glyph, GlyphLabel
  Storage/         FileWriter (atomic file I/O), MatrixRepository (file naming)
  View/            TemplateRenderer
tests/             PHPUnit tests
output/            generated files (git-ignored)
```

## HTTP API

| Method | Path                  | Body / result                                   |
|--------|-----------------------|-------------------------------------------------|
| GET    | `/api/matrix`         | current matrix                                  |
| POST   | `/api/matrix`         | `{"matrix": [[0,1,…], …]}` → writes output.txt  |
| GET    | `/download`           | output.txt as an attachment                     |
| GET    | `/api/glyphs`         | `{"glyphs": ["A", …]}`                          |
| POST   | `/api/glyphs`         | `{"label": "A", "matrix": …}` → output_A.txt    |
| GET    | `/api/glyphs/{label}` | saved glyph                                     |
| DELETE | `/api/glyphs/{label}` | deletes output_{label}.txt                      |
| GET    | `/download/{label}`   | output_{label}.txt as an attachment             |

Rows in `matrix` may be arrays of `0`/`1` or strings such as `"01000000"`.
Invalid input returns `422` with `{"error": "…"}`.
