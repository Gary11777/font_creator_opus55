/**
 * Sends the matrix to the server after every change while guaranteeing the
 * last write wins: at most one request is in flight, and changes made while
 * it runs are coalesced into a single follow-up request.
 */
export class MatrixSync {
    #save;
    #onStatus;
    #pending = null;
    #running = null;

    /**
     * @param {(rows: number[][]) => Promise<unknown>} save
     * @param {(state: 'saving'|'saved'|'error', detail?: Error) => void} onStatus
     */
    constructor(save, onStatus) {
        this.#save = save;
        this.#onStatus = onStatus;
    }

    push(rows) {
        this.#pending = rows;
        this.#running ??= this.#drain().finally(() => {
            this.#running = null;
        });
        return this.#running;
    }

    /** Resolves once every pushed change has been sent. */
    idle() {
        return this.#running ?? Promise.resolve();
    }

    async #drain() {
        while (this.#pending !== null) {
            const rows = this.#pending;
            this.#pending = null;
            this.#onStatus('saving');
            try {
                await this.#save(rows);
                if (this.#pending === null) {
                    this.#onStatus('saved');
                }
            } catch (error) {
                this.#onStatus('error', error);
            }
        }
    }
}
