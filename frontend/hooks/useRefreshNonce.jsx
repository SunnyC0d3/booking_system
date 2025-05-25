import { useEffect } from 'react';
import api from '@api/axiosInstance';

const nonceExpiryRefreshTimer = 1.5 * 60 * 1000;

export function useRefreshNonce(intervalMinutes = nonceExpiryRefreshTimer) {
    useEffect(() => {
        const refreshNonce = async () => {
            try {
                await api.post('/server-token');
            } catch (error) {
                console.log(error);
                throw new Error(error.response?.data?.message || error.message);
            }
        };

        refreshNonce();

        const interval = setInterval(refreshNonce, intervalMinutes);

        return () => clearInterval(interval);
    }, [intervalMinutes]);
}
