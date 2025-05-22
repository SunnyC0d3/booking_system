import React, { useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Login from './components/Login';
import UserDashboard from './components/UserDashboard';
import AdminDashboard from './components/AdminDashboard';
import Payment from './components/Payment.jsx';
import { AuthProvider } from './auth/AuthContext';
import './assets/styles/index.css';
import api from './api/axiosInstance';

const App = () => {
    useEffect(() => {
        const setCookie = async () => {
            try {
                await api.post('/public-token');
                console.log('✅ CSRF cookie set from auth server');
            } catch (err) {
                console.error('❌ Failed to set CSRF cookie', err);
            }
        };

        setCookie();
    }, []);

    return (
        <AuthProvider>
            <Router>
                <Routes>
                    <Route path="/" element={<Login />} />
                    <Route path="/user" element={<UserDashboard />} />
                    <Route path="/admin" element={<AdminDashboard />} />
                    {/*<Route path="/payment" element={<Payment orderId={orderId} orderItems={orderItems} />} />*/}
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </Router>
        </AuthProvider>
    );
};

const rootElement = document.getElementById('app');
const root = ReactDOM.createRoot(rootElement);

root.render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);
