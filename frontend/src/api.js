const API_URL = import.meta.env.DEV
    ? ''
    : (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');

export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

export async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');

    const token = sessionStorage.getItem('hadirku.token');
    if (token) headers.set('Authorization', `Bearer ${token}`);

    const isFormData = options.body instanceof FormData;
    if (options.body && !isFormData && typeof options.body !== 'string') {
        headers.set('Content-Type', 'application/json');
        options = { ...options, body: JSON.stringify(options.body) };
    }

    let response;
    try {
        response = await fetch(`${API_URL}${path}`, { ...options, headers });
    } catch {
        const target = API_URL || 'frontend proxy';
        throw new ApiError(`Backend tidak dapat dihubungi melalui ${target}. Pastikan layanan API berjalan di port 8000.`, 0);
    }

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        if (response.status === 401) {
            sessionStorage.removeItem('hadirku.token');
            sessionStorage.removeItem('hadirku.user');
            window.dispatchEvent(new Event('hadirku:unauthorized'));
        }
        const errors = payload.errors || {};
        const details = Object.values(errors).flat().join(' ');
        throw new ApiError(details || payload.message || 'Permintaan tidak berhasil.', response.status, errors);
    }

    return response;
}

export async function getData(path, options) {
    const response = await api(path, options);
    const payload = await response.json();
    return payload.data;
}

export async function send(path, method, body) {
    const response = await api(path, { method, body });
    const payload = await response.json();
    return payload;
}

export function queryString(values) {
    const params = new URLSearchParams();
    Object.entries(values).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) params.set(key, value);
    });
    const query = params.toString();
    return query ? `?${query}` : '';
}
