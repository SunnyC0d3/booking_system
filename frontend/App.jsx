import React, {Suspense, lazy} from 'react';
import ReactDOM from 'react-dom/client';
import {BrowserRouter as Router, Routes, Route, Navigate} from 'react-router-dom';
import {AuthProvider} from '@context/AuthContext';
import '@assets/styles/index.css';
import {useRefreshNonce} from './hooks/useRefreshNonce.jsx';
import ProtectedRoute from "@components/Wrapper/ProtectedRoute.jsx";
import {BasketProvider} from '@context/BasketContext';

// Lazy imports
const Home = lazy(() => import('@components/Home'));
const Login = lazy(() => import('@components/Login'));
const UserDashboard = lazy(() => import('@components/UserDashboard'));
const AdminDashboard = lazy(() => import('@components/AdminDashboard'));
const Products = lazy(() => import('@components/Products'));
const Basket = lazy(() => import('@components/Basket'));
const ProductDetail = lazy(() => import('@components/ProductDetail'));
const Payment = lazy(() => import('@components/Payment.jsx'));
const NotFound = lazy(() => import('@components/NotFound'));

const App = () => {
    useRefreshNonce();

    return (
        <AuthProvider>
            <BasketProvider>
                <Router>
                    <Suspense fallback={<div>Loading...</div>}>
                        <Routes>
                            <Route path="/" element={<Home/>}/>
                            <Route path="/login" element={<Login/>}/>
                            <Route element={<ProtectedRoute allowedRoles={['Admin', 'Super Admin']}/>}>
                                <Route path="/admin" element={<AdminDashboard/>}/>
                            </Route>
                            <Route element={<ProtectedRoute allowedRoles={['User']}/>}>
                                <Route path="/user" element={<UserDashboard/>}/>
                            </Route>
                            <Route path="/products" element={<Products/>}/>
                            <Route path="/products/:product" element={<ProductDetail/>}/>
                            <Route path="/basket" element={<Basket />} />
                            <Route path="/payment/:orderId" element={<Payment />} />
                            <Route path="*" element={<NotFound/>}/>
                        </Routes>
                    </Suspense>
                </Router>
            </BasketProvider>
        </AuthProvider>
    );
};

const rootElement = document.getElementById('app');
const root = ReactDOM.createRoot(rootElement);

root.render(
    <React.StrictMode>
        <App/>
    </React.StrictMode>
);
