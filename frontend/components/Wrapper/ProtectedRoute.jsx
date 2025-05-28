import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import useAuth from '@hooks/useAuth';

const ProtectedRoute = ({ allowedRoles }) => {
    const { authenticated, user } = useAuth();

    if (!authenticated) {
        return <Navigate to="/login" replace />;
    }

    if (!allowedRoles.includes(user?.role)) {
        return <Navigate to="/" replace />;
    }

    return <Outlet />;
};

export default ProtectedRoute;
