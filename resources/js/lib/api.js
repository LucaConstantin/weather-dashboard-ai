// Every call returns { ok, status, data, message } and never throws, so a failing
// card or image request can show its own message while the rest keeps working.
export async function request(url, { method = 'GET', body, timeoutMs = 20000 } = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
            signal: controller.signal,
        });

        let data = null;
        try {
            data = await response.json();
        } catch {
            // not JSON, for example a PHP error page
        }

        if (!response.ok) {
            return { ok: false, status: response.status, data, message: messageFrom(data, response.status) };
        }

        return { ok: true, status: response.status, data, message: null };
    } catch (error) {
        const message = error.name === 'AbortError'
            ? 'The server took too long to answer. Try again.'
            : 'Could not reach the server. Is it still running?';

        return { ok: false, status: 0, data: null, message };
    } finally {
        clearTimeout(timer);
    }
}

export const getJson = (url, options) => request(url, options);
export const postJson = (url, body, options) => request(url, { ...options, method: 'POST', body });

function messageFrom(data, status) {
    if (data?.errors) {
        return Object.values(data.errors).flat()[0];
    }
    if (data?.message && status !== 500) {
        return data.message;
    }
    if (status === 429) {
        return 'Too many requests. Wait a minute and try again.';
    }

    return `Something went wrong on the server (${status}).`;
}
