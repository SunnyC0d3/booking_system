import React from 'react';
import { AlertTriangle } from 'lucide-react';

const ErrorMessage = ({ message }) => {
    if (!message) return null;

    return (
        <div className="min-h-screen py-10 px-4">
            <div className="max-w-2xl mx-auto bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-md flex items-start space-x-3 shadow-sm">
                <AlertTriangle className="w-5 h-5 text-red-500 mt-1" />
                <div>
                    <p className="font-semibold">Something went wrong</p>
                    <p className="text-sm mt-1">{message}</p>
                </div>
            </div>
        </div>
    );
};

export default ErrorMessage;