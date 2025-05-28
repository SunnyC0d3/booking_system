import React from 'react';
import { useNavigate } from 'react-router-dom';
import useAuth from '@hooks/useAuth';

const Home = () => {
    const { user, authenticated, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogin = () => {
        navigate('/login');
    };

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-100">
            <div className="bg-white p-8 rounded shadow-md w-full max-w-md text-center">
                {authenticated ? (
                    <>
                        <h2 className="text-2xl font-bold mb-4">Welcome, {user?.name || 'User'}!</h2>
                        <p className="mb-6 text-gray-600">You are logged in.</p>
                        <button
                            onClick={handleLogout}
                            className="w-full bg-red-500 text-white py-2 rounded hover:bg-red-600"
                        >
                            Logout
                        </button>
                    </>
                ) : (
                    <>
                        <h2 className="text-2xl font-bold mb-4">Welcome to Our App</h2>
                        <p className="mb-6 text-gray-600">Please log in to continue.</p>
                        <button
                            onClick={handleLogin}
                            className="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700"
                        >
                            Login
                        </button>
                    </>
                )}
            </div>
        </div>
    );
};

export default Home;
