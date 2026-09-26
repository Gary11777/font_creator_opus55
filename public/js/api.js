export class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
    }
}

/**
 * Thin wrapper around the PHP JSON API.
 */
export class ApiClient {
    #baseUrl;

    constructor(baseUrl = '') {
        this.#baseUrl = baseUrl;
    }

    saveMatrix(matrix) {
        return this.#request('POST', '/api/matrix', { matrix });
    }

    listGlyphs() {
        return this.#request('GET', '/api/glyphs');
    }

    loadGlyph(label) {
        return this.#request('GET', `/api/glyphs/${encodeURIComponent(label)}`);
    }

    saveGlyph(label, matrix) {
        return this.#request('POST', '/api/glyphs', { label, matrix });
    }

    deleteGlyph(label) {
        return this.#request('DELETE', `/api/glyphs/${encodeURIComponent(label)}`);
    }

    glyphDownloadUrl(label) {
        return `${this.#baseUrl}/download/${encodeURIComponent(label)}`;
    }

    async #request(method, path, body) {
        const options = { method, headers: { Accept: 'application/json' } };
        if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        let response;
        try {
            response = await fetch(this.#baseUrl + path, options);
        } catch {
            throw new ApiError('Cannot reach the server.', 0);
        }

        if (response.status === 204) {
            return null;
        }

        const data = await response.json().catch(() => null);
        if (!response.ok) {
            throw new ApiError(data?.error ?? `Request failed (HTTP ${response.status}).`, response.status);
        }

        return data;
    }
}
