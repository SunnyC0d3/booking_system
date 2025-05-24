import React, {createContext, useContext, useState, useEffect} from 'react';
import callApi from '@api/callApi.jsx';
import api from '@api/axiosInstance';

const AuthContext = createContext();

export const AuthProvider = ({children}) => {
    const [accessToken, setAccessToken] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        api.interceptors.request.use(
            (config) => {
                if (accessToken) {
                    config.headers.Authorization = `Bearer ${accessToken}`;
                }
                return config;
            },
            (error) => Promise.reject(error)
        );

        api.interceptors.response.use(
            (response) => response,
            async (error) => {
                const originalRequest = error.config;

                if (error.response?.status === 401 && !originalRequest._retry) {
                    originalRequest._retry = true;

                    try {
                        const response = await callApi({
                            method: 'POST',
                            path: '/api/refresh-token',
                            authType: 'client',
                            data: {}
                        });

                        setAccessToken(response.data.access_token);
                        originalRequest.headers.Authorization = `Bearer ${response.data.access_token}`;

                        return api(originalRequest);
                    } catch (error) {
                        setAccessToken(null);

                        return Promise.reject(error);
                    }
                }

                return Promise.reject(error);
            }
        );
    }, [accessToken]);

    const login = async (email, password) => {
        setLoading(true);

        try {
            const response = await callApi({
                method: 'POST',
                path: '/api/login',
                authType: 'client',
                data: {email, password}
            });

            setAccessToken(response.data.access_token);
            setLoading(false);

            return {success: true, message: 'User logged in.'};
        } catch (error) {
            throw new Error(error.response?.data?.message || error.message);
        }
    };

    const logout = async () => {
        try {
            await callApi({
                method: 'POST',
                path: '/api/logout'
            });
            setUser(null);
        } catch (error) {
            throw new Error(error.response?.data?.message || error.message);
        }
    };

    return (
        <AuthContext.Provider value={{login, logout, loading}}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuthContext = () => useContext(AuthContext);
