import { ApiClient } from './api.js';
import { Matrix } from './matrix.js';
import { MatrixSync } from './sync.js';

/**
 * Keeps the <table class="matrix8"> in sync with a Matrix instance.
 */
class MatrixView {
    #table;
    #onToggle;

    constructor(table, onToggle) {
        this.#table = table;
        this.#onToggle = onToggle;
        table.addEventListener('click', (event) => {
            const cell = event.target.closest('td[data-row][data-col]');
            if (cell && table.contains(cell)) {
                this.#onToggle(Number(cell.dataset.row), Number(cell.dataset.col));
            }
        });
    }

    readState() {
        const rows = [...this.#table.tBodies[0].rows].map((tr) =>
            [...tr.cells].map((td) => (td.classList.contains('is-on') ? 1 : 0)),
        );
        return Matrix.fromRows(rows);
    }

    renderCell(row, col, on) {
        const td = this.#table.tBodies[0].rows[row].cells[col];
        td.classList.toggle('is-on', on);
        td.querySelector('button')?.setAttribute('aria-pressed', String(on));
    }

    render(matrix) {
        for (let r = 0; r < matrix.size; r++) {
            for (let c = 0; c < matrix.size; c++) {
                this.renderCell(r, c, matrix.get(r, c));
            }
        }
    }
}

/**
 * The list of saved (labelled) characters.
 */
class GlyphListView {
    #list;
    #empty;
    #template;
    #api;

    constructor({ list, empty, template, api, onLoad, onDelete }) {
        this.#list = list;
        this.#empty = empty;
        this.#template = template;
        this.#api = api;
        list.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-action]');
            const label = button?.closest('li')?.dataset.label;
            if (!label) return;
            if (button.dataset.action === 'load') onLoad(label);
            if (button.dataset.action === 'delete') onDelete(label);
        });
    }

    render(labels) {
        const items = labels.map((label) => {
            const item = this.#template.content.firstElementChild.cloneNode(true);
            item.dataset.label = label;
            item.querySelector('.glyph-item__label').textContent = label;
            item.querySelector('[data-action="download"]').href = this.#api.glyphDownloadUrl(label);
            return item;
        });
        this.#list.replaceChildren(...items);
        this.#empty.hidden = labels.length > 0;
    }
}

function createStatus(element) {
    let timer;
    return (message, kind = 'info') => {
        clearTimeout(timer);
        element.textContent = message;
        element.classList.toggle('is-error', kind === 'error');
        element.classList.toggle('is-success', kind === 'success');
        if (kind === 'success') {
            timer = setTimeout(() => {
                element.textContent = '';
            }, 2500);
        }
    };
}

function init() {
    const $ = (selector) => document.querySelector(selector);
    const api = new ApiClient();
    const showStatus = createStatus($('#status'));
    const preview = $('#preview');

    const sync = new MatrixSync(
        (rows) => api.saveMatrix(rows),
        (state, error) => {
            if (state === 'saving') showStatus('Saving…');
            if (state === 'saved') showStatus('Saved to output/output.txt', 'success');
            if (state === 'error') showStatus(`Save failed: ${error.message}`, 'error');
        },
    );

    const view = new MatrixView($('#matrix'), (row, col) => {
        view.renderCell(row, col, matrix.toggle(row, col));
        commit();
    });
    let matrix = view.readState();

    function commit() {
        preview.textContent = matrix.toText();
        return sync.push(matrix.toArray());
    }

    function replaceMatrix(next) {
        matrix = next;
        view.render(matrix);
        return commit();
    }

    const glyphs = new GlyphListView({
        list: $('#glyph-list'),
        empty: $('#glyph-empty'),
        template: $('#glyph-item-template'),
        api,
        onLoad: async (label) => {
            try {
                const glyph = await api.loadGlyph(label);
                await replaceMatrix(Matrix.fromRows(glyph.matrix));
                showStatus(`Loaded "${label}" into the editor.`, 'success');
            } catch (error) {
                showStatus(`Could not load "${label}": ${error.message}`, 'error');
            }
        },
        onDelete: async (label) => {
            if (!confirm(`Delete saved character "${label}"?`)) return;
            try {
                await api.deleteGlyph(label);
                await refreshGlyphs();
                showStatus(`Deleted "${label}".`, 'success');
            } catch (error) {
                showStatus(`Could not delete "${label}": ${error.message}`, 'error');
            }
        },
    });

    async function refreshGlyphs() {
        const { glyphs: labels } = await api.listGlyphs();
        glyphs.render(labels);
    }

    glyphs.render(JSON.parse($('#glyph-list').dataset.glyphs || '[]'));

    $('#reset-button').addEventListener('click', () => replaceMatrix(new Matrix(matrix.size)));

    $('#download-button').addEventListener('click', async (event) => {
        const link = event.currentTarget;
        if (link.dataset.ready) {
            delete link.dataset.ready;
            return;
        }
        event.preventDefault();
        await sync.idle();
        link.dataset.ready = '1';
        link.click();
    });

    $('#save-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = event.currentTarget.elements.label;
        const label = input.value.trim();
        try {
            const saved = await api.saveGlyph(label, matrix.toArray());
            await refreshGlyphs();
            showStatus(`Saved as output/${saved.file}`, 'success');
            input.value = '';
        } catch (error) {
            showStatus(`Could not save "${label}": ${error.message}`, 'error');
        }
    });
}

init();
