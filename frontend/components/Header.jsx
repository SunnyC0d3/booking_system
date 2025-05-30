import React, {useState} from 'react';
import {useNavigate} from 'react-router-dom';
import useAuth from '@hooks/useAuth';
import {UserCircle, LogOut, LogIn, Menu, X} from 'lucide-react';

const Header = () => {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const {user, authenticated, logout} = useAuth();
    const navigate = useNavigate();

    const handleLogin = () => {
        navigate('/login');
    };

    const handleLogout = () => {
        logout();
        navigate('/');
    };

    const toggleMenu = () => {
        setIsMenuOpen(!isMenuOpen);
    };

    const navigateAndClose = (path) => {
        navigate(path);
        setIsMenuOpen(false);
    };

    return (
        <>
            <header className="flex justify-between items-center px-6 py-4 shadow-sm bg-white relative z-50">
                <h1 className="text-xl font-bold text-indigo-600 cursor-pointer" onClick={() => navigate('/')}>
                    TemplateHub
                </h1>
                <div className="flex items-center space-x-4">
                    <button onClick={toggleMenu}>
                        <Menu className="w-6 h-6 text-gray-800" />
                    </button>
                </div>
            </header>

            {/* Slide-In Menu */}
            <div
                className={`fixed top-0 right-0 h-full w-64 bg-white shadow-lg transform transition-transform duration-300 z-40 ${
                    isMenuOpen ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                <div className="flex justify-between items-center px-6 py-4 border-b">
                    <span className="text-lg font-semibold text-indigo-700">Menu</span>
                    <button onClick={toggleMenu}>
                        <X className="w-5 h-5 text-gray-700" />
                    </button>
                </div>
                <nav className="flex flex-col px-6 py-4 space-y-4">
                    <button onClick={() => navigateAndClose('/')} className="text-gray-700 hover:text-indigo-600 text-left">
                        Home
                    </button>
                    <button onClick={() => navigateAndClose('/products')} className="text-gray-700 hover:text-indigo-600 text-left">
                        Products
                    </button>

                    {authenticated ? (
                        <>
                            <div className="flex items-center space-x-2 mt-6">
                                <UserCircle className="w-5 h-5 text-gray-600" />
                                <span className="text-gray-700 font-medium">{user?.name || 'User'}</span>
                            </div>
                            <button
                                onClick={() => {
                                    handleLogout();
                                    toggleMenu();
                                }}
                                className="mt-2 bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600"
                            >
                                Logout
                            </button>
                        </>
                    ) : (
                        <button
                            onClick={() => {
                                handleLogin();
                                toggleMenu();
                            }}
                            className="mt-6 bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                        >
                            Login
                        </button>
                    )}
                </nav>
            </div>

            {/* Backdrop when menu is open */}
            {isMenuOpen && (
                <div
                    className="fixed inset-0 bg-black bg-opacity-30 z-30"
                    onClick={toggleMenu}
                />
            )}
        </>
    );
};

export default Header;