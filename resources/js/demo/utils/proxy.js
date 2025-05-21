export const proxyLogin = async (credentials) => {
    const proxyToken = document.querySelector('meta[name="x-proxy-token"]')?.getAttribute('content');

    const response = await fetch('/api/proxy/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Proxy-Token': proxyToken
        },
        body: JSON.stringify(credentials)
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Login failed');
    }

    return response.json();
};
