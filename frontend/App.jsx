import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Home from '@components/Home';
import Login from '@components/Login';
import UserDashboard from '@components/UserDashboard';
import AdminDashboard from '@components/AdminDashboard';
import Payment from '@components/Payment.jsx';
import { AuthProvider } from '@auth/AuthContext';
import '@assets/styles/index.css';
import {useRefreshNonce} from './hooks/useRefreshNonce.jsx';
import ProtectedRoute from "@components/Wrapper/ProtectedRoute.jsx";

const App = () => {
    useRefreshNonce();

    return (
        <AuthProvider>
            <Router>
                <Routes>
                    <Route path="/" element={<Home />} />
                    <Route path="/login" element={<Login />} />
                    <Route element={<ProtectedRoute allowedRoles={['Admin', 'Super Admin']} />}>
                        <Route path="/admin" element={<AdminDashboard />} />
                    </Route>
                    <Route element={<ProtectedRoute allowedRoles={['User']} />}>
                        <Route path="/user" element={<UserDashboard />} />
                    </Route>
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
