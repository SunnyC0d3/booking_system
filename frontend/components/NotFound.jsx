// @components/NotFound.jsx
import React from 'react';
import { useNavigate } from 'react-router-dom';
import Container from '@components/Wrapper/Container';

const NotFound = () => {
    const navigate = useNavigate();

    return (
        <Container>
            <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4 text-center">
                <h1 className="text-6xl font-bold text-indigo-600 mb-4">404</h1>
                <p className="text-2xl font-semibold text-gray-800 mb-2">Page Not Found</p>
                <p className="text-gray-500 mb-6">
                    Sorry, the page you're looking for doesn't exist or has been moved.
                </p>
                <button
                    onClick={() => navigate('/')}
                    className="px-6 py-3 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition"
                >
                    Go Back Home
                </button>
            </div>
        </Container>
    );
};

export default NotFound;
