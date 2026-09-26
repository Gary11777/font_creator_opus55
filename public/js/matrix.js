/**
 * Client-side matrix state. Cells are stored as 0/1 to match the file format.
 */
export class Matrix {
    #size;
    #cells;

    constructor(size = 8) {
        this.#size = size;
        this.#cells = Array.from({ length: size }, () => new Array(size).fill(0));
    }

    static fromRows(rows) {
        const matrix = new Matrix(rows.length);
        rows.forEach((row, r) => row.forEach((value, c) => matrix.set(r, c, value)));
        return matrix;
    }

    get size() {
        return this.#size;
    }

    get(row, col) {
        return this.#cells[row][col] === 1;
    }

    set(row, col, on) {
        this.#cells[row][col] = on ? 1 : 0;
    }

    toggle(row, col) {
        this.set(row, col, !this.get(row, col));
        return this.get(row, col);
    }

    clear() {
        this.#cells.forEach((row) => row.fill(0));
    }

    toArray() {
        return this.#cells.map((row) => [...row]);
    }

    toText() {
        return this.#cells.map((row) => row.join('')).join('\n') + '\n';
    }
}
