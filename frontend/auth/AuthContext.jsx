import React, { createContext, useContext, useState, useEffect } from 'react';
import callApi from "@api/callApi.jsx";

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);

    const login = async (email, password) => {
        try {
            const response = await callApi({
                method: 'POST',
                url: '/login',
                data: { email, password }
            });
            setUser(response.data.user);
            return { success: true, role: response.data.user.role };
        } catch (error) {
            return { success: false, message: error.response?.data?.message || 'Login failed' };
        }
    };

    const logout = async () => {
        try {
            await callApi({ method: 'POST', url: '/logout' });
            setUser(null);
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Login failed'
            };
        }
    };

    return (
        <AuthContext.Provider value={{ user, login, logout }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuthContext = () => useContext(AuthContext);
