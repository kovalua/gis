export class ApiClient {
    constructor(baseUrl) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.tokenKey = 'gis_admin_token';
        this.userKey = 'gis_admin_user';
    }

    get token() {
        return localStorage.getItem(this.tokenKey);
    }

    set token(value) {
        if (value) localStorage.setItem(this.tokenKey, value);
        else localStorage.removeItem(this.tokenKey);
    }

    get user() {
        const raw = localStorage.getItem(this.userKey);
        return raw ? JSON.parse(raw) : null;
    }

    set user(value) {
        if (value) localStorage.setItem(this.userKey, JSON.stringify(value));
        else localStorage.removeItem(this.userKey);
    }

    clearAuth() {
        this.token = null;
        this.user = null;
    }

    async request(path, options = {}) {
        const headers = {
            'Accept': 'application/json',
            ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {}),
            ...(options.headers || {}),
        };

        const response = await fetch(`${this.baseUrl}${path}`, {
            ...options,
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

    login(email, password) {
        return this.request('/auth/login', { method: 'POST', body: { email, password } });
    }

    me() {
        return this.request('/auth/me');
    }

    logout() {
        return this.request('/auth/logout', { method: 'POST' });
    }

    get(path) { return this.request(path); }
    post(path, body) { return this.request(path, { method: 'POST', body }); }
    put(path, body) { return this.request(path, { method: 'PUT', body }); }
    delete(path) { return this.request(path, { method: 'DELETE' }); }
}