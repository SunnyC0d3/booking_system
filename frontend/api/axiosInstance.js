import axios from 'axios';
import {checkAccessTokenExpiry} from '@assets/helper';

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
        if (!checkAccessTokenExpiry()) {
            return Promise.reject(new Error('Access token expired'));
        }

        const token = localStorage.getItem('access_token');
        config.headers.Authorization = `Bearer ${token}`;

        return config;
    },
    (error) => Promise.reject(error)
);

export default api;
