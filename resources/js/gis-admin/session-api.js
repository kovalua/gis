export class ApiClient {
    constructor(baseUrl) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
    }

    async request(path, options = {}) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            ...(options.headers || {}),
        };

        const response = await fetch(`${this.baseUrl}${path}`, {
            ...options,
            credentials: 'same-origin',
            headers,
            body: options.body && !(options.body instanceof FormData)
                ? JSON.stringify(options.body)
                : options.body,
        });

        const text = await response.text();
        const json = text ? JSON.parse(text) : null;

        if (!response.ok) {
            const message = json?.error?.message || json?.message || `HTTP ${response.status}`;
            const error = new Error(message);
            error.status = response.status;
            error.payload = json;
            throw error;
        }

        return json;
    }

    get(path) { return this.request(path); }
    post(path, body) { return this.request(path, { method: 'POST', body }); }
    put(path, body) { return this.request(path, { method: 'PUT', body }); }
    delete(path) { return this.request(path, { method: 'DELETE' }); }
}