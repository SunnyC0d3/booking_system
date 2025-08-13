'use client';

import React, { createContext, useEffect, useState } from 'react';
import { clientTokenManager } from '@/lib/clientTokenManager';

interface ClientTokenContextType {
    isInitialized: boolean;
    isError: boolean;
    retry: () => Promise<void>;
}

const ClientTokenContext = createContext<ClientTokenContextType>({
    isInitialized: false,
    isError: false,
    retry: async () => {},
});

interface ClientTokenProviderProps {
    children: React.ReactNode;
}

export const ClientTokenProvider: React.FC<ClientTokenProviderProps> = ({ children }) => {
    const [isInitialized, setIsInitialized] = useState(false);
    const [isError, setIsError] = useState(false);

    const initializeClientToken = async () => {
        try {
            setIsError(false);

            await clientTokenManager.getValidToken();

            setIsInitialized(true);
        } catch (error) {
            setIsError(true);
            setIsInitialized(false);
        }
    };

    const retry = async () => {
        await initializeClientToken();
    };

    useEffect(() => {
        initializeClientToken();

        const interval = setInterval(async () => {
            try {
                await clientTokenManager.getValidToken();
            } catch (error) {
                setIsError(true);
                setIsInitialized(false);
            }
        }, 30 * 60 * 1000);

        return () => clearInterval(interval);
    }, []);

    return (
        <ClientTokenContext.Provider value={{ isInitialized, isError, retry }}>
            {children}
        </ClientTokenContext.Provider>
    );
};