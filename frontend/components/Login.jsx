import React, {useState, useEffect} from 'react';
import {useNavigate} from 'react-router-dom';
import useAuth from '@hooks/useAuth';

const Login = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState(null);
    const navigate = useNavigate();
    const {user, login, authenticated, getRedirectPath} = useAuth();

    useEffect(() => {
        if (authenticated && user) {
            navigate(getRedirectPath());
        }
    }, [authenticated, user]);

    const handleSubmit = async (e) => {
        e.preventDefault();

        try {
            const result = await login(email, password);

            if (!result.success) {
                setError(result.message);
            }
        } catch (err) {
            setError(err.message);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-100">
            <form onSubmit={handleSubmit} className="bg-white p-8 rounded shadow-md w-full max-w-md">
                <h2 className="text-2xl font-bold mb-6 text-center">Login</h2>

                {error && <div className="mb-4 text-red-600 text-sm text-center">{error}</div>}

                <input
                    type="email"
                    placeholder="Email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    className="w-full mb-4 p-2 border rounded"
                />
                <input
                    type="password"
                    placeholder="Password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    className="w-full mb-6 p-2 border rounded"
                />
                <button type="submit" className="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">
                    Login
                </button>
            </form>
        </div>
    );
};

export default Login;
