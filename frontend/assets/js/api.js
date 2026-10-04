function getApiBaseUrl() {
    const pathname = window.location.pathname;
    const match = pathname.match(/^(.*?)\/(?:frontend|admin|dispatcher|driver|clinic|api)(?:\/|$)/);
    if (match && match[1]) {
        return `${match[1]}/api`;
    }
    return pathname.startsWith('/medline-rural') ? '/medline-rural/api' : '/api';
}

const API_BASE_URL = getApiBaseUrl();

async function requestJson(path, options = {}) {
    const response = await fetch(`${API_BASE_URL}/${path.replace(/^\/+/, '')}`, options);
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : await response.text();

    if (!response.ok) {
        const message = typeof payload === 'object' && payload.message
            ? payload.message
            : 'The request could not be completed.';
        const error = new Error(message);
        error.status = response.status;
        throw error;
    }

    return payload;
}

window.MedLineAPI = {
    baseUrl: API_BASE_URL,
    requestJson
};
