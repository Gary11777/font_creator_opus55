<?php
/**
 * @var int $size
 * @var \FontCreator\Model\Matrix $matrix
 * @var list<string> $glyphs
 * @var \Closure(mixed): string $e
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Font Creator &middot; <?= $size ?>&times;<?= $size ?> pixel editor</title>
    <link rel="stylesheet" href="/css/style.css">
    <script type="module" src="/js/app.js"></script>
</head>
<body>
<main class="app">
    <header class="app__header">
        <h1>Font Creator</h1>
        <p>Click cells to draw an <?= $size ?>&times;<?= $size ?> character. Every change is saved to <code>output/output.txt</code>.</p>
    </header>

    <section class="editor" aria-labelledby="editor-title">
        <h2 id="editor-title" class="visually-hidden">Editor</h2>

        <table class="matrix8" id="matrix" aria-label="<?= $size ?> by <?= $size ?> pixel matrix">
            <tbody>
            <?php for ($row = 0; $row < $size; $row++): ?>
                <tr>
                <?php for ($col = 0; $col < $size; $col++): $on = $matrix->isSet($row, $col); ?>
                    <td class="<?= $on ? 'is-on' : '' ?>" data-row="<?= $row ?>" data-col="<?= $col ?>">
                        <button type="button" class="cell" aria-pressed="<?= $on ? 'true' : 'false' ?>"
                                aria-label="Row <?= $row + 1 ?>, column <?= $col + 1 ?>"></button>
                    </td>
                <?php endfor; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>

        <div class="toolbar">
            <button type="button" class="button" id="reset-button">Reset</button>
            <a class="button" id="download-button" href="/download" download>Download output.txt</a>
        </div>

        <p class="status" id="status" role="status" aria-live="polite"></p>
    </section>

    <aside class="panel">
        <section aria-labelledby="preview-title">
            <h2 id="preview-title">output.txt</h2>
            <pre class="preview" id="preview"><?= $e($matrix->toText()) ?></pre>
        </section>

        <section aria-labelledby="glyphs-title">
            <h2 id="glyphs-title">Saved characters</h2>
            <form class="save-form" id="save-form">
                <label for="glyph-label">Label</label>
                <input type="text" id="glyph-label" name="label" required maxlength="32"
                       pattern="[A-Za-z0-9_\-]{1,32}" placeholder="e.g. A or 1"
                       title="Letters, digits, &quot;_&quot; or &quot;-&quot; (max 32)">
                <button type="submit" class="button button--primary">Save</button>
            </form>
            <p class="hint">Saved as <code>output/output_&lt;label&gt;.txt</code>. Saving an existing label overwrites it.</p>

            <ul class="glyph-list" id="glyph-list" data-glyphs="<?= $e(json_encode($glyphs, JSON_THROW_ON_ERROR)) ?>"></ul>
            <p class="empty" id="glyph-empty">No saved characters yet.</p>

            <template id="glyph-item-template">
                <li class="glyph-item">
                    <span class="glyph-item__label"></span>
                    <button type="button" class="button button--small" data-action="load">Load</button>
                    <a class="button button--small" data-action="download" download>Download</a>
                    <button type="button" class="button button--small button--danger" data-action="delete">Delete</button>
                </li>
            </template>
        </section>
    </aside>
</main>
</body>
</html>
