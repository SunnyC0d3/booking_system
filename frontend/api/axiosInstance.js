import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_AUTH_SERVER_BASE_URL,
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
    }
});

api.interceptors.request.use(
    (config) => {
        if(localStorage.getItem('access_token')) {
            const token = localStorage.getItem('access_token');
            config.headers.Authorization = `Bearer ${token}`;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

export default api;
