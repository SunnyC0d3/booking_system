import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
    }
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const proxyToken = document.querySelector('meta[name="x-proxy-token"]')?.getAttribute('content');

    if (config.url === '/login' || config.url === '/register') {
        config.headers['X-Proxy-Token'] = proxyToken;
    }

    return config;
});

export default api;
