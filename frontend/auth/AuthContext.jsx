import React, {createContext, useContext, useState, useEffect} from 'react';
import callApi from '@api/callApi.jsx';
import {checkAccessTokenExpiry} from '@assets/helper';

const AuthContext = createContext();

export const AuthProvider = ({children}) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(false);

    const isAuthenticated = () => {
        checkAccessTokenExpiry();
    };

    const getRedirectPath = () => {
        if (!isAuthenticated() || !user)
            return '/';

        if (user.role === 'Admin')
            return '/admin';

        if (user.role === 'User')
            return '/user';
    };

    const login = async (email, password) => {
        setLoading(true);

        try {
            const response = await callApi({
                method: 'POST',
                path: '/api/login',
                authType: 'client',
                data: {email, password}
            });

            const expiresInSeconds = response.data.expires_in;
            const expiryTime = Date.now() + expiresInSeconds * 1000;

            localStorage.setItem('access_token', response.data.access_token);
            localStorage.setItem('access_token_expiry', expiryTime);

            setUser(response.data.user);
            setLoading(false);

            return {success: true, user: response.data.user, message: 'User logged in.'};
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
            localStorage.removeItem('access_token');
            localStorage.removeItem('access_token_expiry');
        } catch (error) {
            throw new Error(error.response?.data?.message || error.message);
        }
    };

    return (
        <AuthContext.Provider value={{
            login,
            logout,
            isAuthenticated,
            getRedirectPath,
            user,
            loading
        }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuthContext = () => useContext(AuthContext);
