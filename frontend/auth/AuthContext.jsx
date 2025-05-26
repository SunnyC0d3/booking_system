import React, {createContext, useContext, useState, useEffect} from 'react';
import callApi from '@api/callApi.jsx';
import {
    checkAccessTokenExpiry,
    checkIfUserWasLoggedIn
} from '@assets/helper';

const AuthContext = createContext();

export const AuthProvider = ({children}) => {
    const [authenticated, setAuthenticated] = useState(false);
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if(checkIfUserWasLoggedIn() && checkAccessTokenExpiry()) {
            setUser(JSON.parse(localStorage.getItem('user')));
            setAuthenticated(true);
        } else {
            setAuthenticated(false);
        }
    }, [authenticated]);

    const getRedirectPath = () => {
        if (!authenticated)
            return '/';

        if (user['role'] === 'Admin')
            return '/admin';

        if (user['role'] === 'User')
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

            const expiresInSeconds = Math.floor(response.data.expires_at);
            const expiryTime = Date.now() + expiresInSeconds * 1000;

            localStorage.setItem('access_token', response.data.access_token);
            localStorage.setItem('access_token_expiry', expiryTime.toString());
            localStorage.setItem('user', JSON.stringify(response.data.user));

            setAuthenticated(true);
            setUser(response.data.user);
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
            setAuthenticated(false);
            setUser(null);
            localStorage.removeItem('access_token');
            localStorage.removeItem('access_token_expiry');
            localStorage.removeItem('user');
        } catch (error) {
            throw new Error(error.response?.data?.message || error.message);
        }
    };

    return (
        <AuthContext.Provider value={{
            login,
            logout,
            getRedirectPath,
            authenticated,
            user,
            loading
        }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuthContext = () => useContext(AuthContext);
