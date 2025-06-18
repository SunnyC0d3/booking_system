import React, {useState, useEffect} from 'react';
import Container from '@components/Wrapper/Container';
import {useAuthContext} from '@context/AuthContext';
import callApi from '@api/callApi';
import ErrorMessage from '@components/ErrorMessage';
import {AlertTriangle} from "lucide-react";

const TABS = ['Profile', 'Orders'];

const OrderItemWithReturn = ({ item, orderStatus }) => {
    const [showForm, setShowForm] = useState(false);
    const [reason, setReason] = useState('');
    const [orderReturn, setOrderReturn] = useState(item.order_returns || null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    console.log(item);

    const handleSubmit = async () => {
        setLoading(true);
        setError(null);
        setSuccess(false);

        try {
            const res = await callApi({
                path: '/api/returns',
                method: 'POST',
                authType: 'auth',
                data: {
                    order_item_id: item.id,
                    reason,
                },
            });

            setOrderReturn(res.data);
            setSuccess(true);
            setShowForm(false);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="border-t pt-3 mt-3">
            <p><strong>Name:</strong> {item.product.name}</p>
            <p><strong>Qty:</strong> {item.quantity}</p>
            <p><strong>Price:</strong> £{(item.product.price / 100).toFixed(2)}</p>

            {orderReturn ? (
                <div className="mt-2 bg-gray-100 p-3 rounded">
                    <p><strong>Return Status:</strong> <span className="text-indigo-600">{orderReturn.status}</span></p>
                    <p><strong>Message:</strong> {orderReturn.reason}</p>
                    <p className="text-sm text-gray-500">Submitted on: {new Date(orderReturn.created_at).toLocaleString()}</p>
                </div>
            ) : (
                <>
                    {!showForm && orderStatus !== 'Pending Payment' && (
                        <button
                            onClick={() => setShowForm(true)}
                            className="mt-2 px-3 py-1 text-sm bg-yellow-500 text-white rounded hover:bg-yellow-600"
                        >
                            Create Return
                        </button>
                    )}

                    {showForm && (
                        <div className="mt-2 space-y-2">
                            {error && (
                                <div className="max-w-full bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-md flex items-start space-x-3 shadow-sm">
                                    <AlertTriangle className="w-5 h-5 text-red-500 mt-1" />
                                    <div>
                                        <p className="font-semibold">{error}</p>
                                    </div>
                                </div>
                            )}

                            <textarea
                                className="w-full border rounded p-2"
                                rows={3}
                                value={reason}
                                onChange={e => setReason(e.target.value)}
                                placeholder="Enter return reason..."
                                disabled={loading}
                            />

                            <div className="flex gap-2">
                                <button
                                    onClick={handleSubmit}
                                    className="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                                    disabled={loading || reason.trim() === ''}
                                >
                                    {loading ? 'Submitting...' : 'Submit'}
                                </button>
                                <button
                                    onClick={() => setShowForm(false)}
                                    className="px-3 py-1 text-sm bg-gray-300 rounded hover:bg-gray-400"
                                    disabled={loading}
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
};

const UserDashboard = () => {
    const {user} = useAuthContext();
    const [error, setError] = useState(null);
    const [activeTab, setActiveTab] = useState('Profile');
    const [orders, setOrders] = useState([]);

    useEffect(() => {
        if (activeTab === 'Orders') fetchOrders();
    }, [activeTab]);

    const fetchOrders = async () => {
        try {
            const res = await callApi({
                path: '/api/orders',
                authType: 'auth',
                data: {
                    user_id: user['id']
                }
            });

            setOrders(res.data);
        } catch (err) {
            setError(err.message);
            console.error('Failed to load orders', err);
        }
    };

    const renderContent = () => {
        switch (activeTab) {
            case 'Profile':
                return (
                    <div>
                        <h2 className="text-xl font-bold mb-2">Personal Info</h2>
                        <p><strong>Email:</strong> {user.email}</p>
                        <p><strong>Role:</strong> {user.role}</p>
                    </div>
                );
            case 'Orders':
                return (
                    <div>
                        <h2 className="text-xl font-bold mb-4">Your Orders</h2>
                        {orders.length === 0 ? (
                            <p>No orders yet.</p>
                        ) : (
                            <ul className="space-y-3">
                                {orders.map(order => (
                                    <li key={order.id} className="border p-4 rounded bg-white shadow-sm">
                                        <p><strong>Order #</strong> {order.id}</p>
                                        <p>Status: <span className="text-sm text-indigo-600">{order.status}</span>
                                        </p>
                                        <p>Date: {new Date(order.created_at).toLocaleDateString()}</p>
                                        <p>Total: £{(order.total_amount / 100).toFixed(2)}</p>

                                        <div className="mt-4 space-y-3">
                                            {order.orderItem.map(item => (
                                                <OrderItemWithReturn key={item.id} item={item} orderStatus={order.status} />
                                            ))}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <Container>
            {error && <ErrorMessage message={error}/>}
            {!error && (
                <div className="min-h-screen bg-gray-100 py-10">
                    <div className="max-w-4xl mx-auto bg-white p-6 rounded shadow">
                        <h1 className="text-2xl font-bold mb-6">User Dashboard</h1>
                        <div className="flex space-x-4 mb-6">
                            {TABS.map(tab => (
                                <button
                                    key={tab}
                                    onClick={() => setActiveTab(tab)}
                                    className={`px-4 py-2 rounded ${
                                        activeTab === tab
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-200 text-gray-800'
                                    }`}
                                >
                                    {tab}
                                </button>
                            ))}
                        </div>
                        <div>{renderContent()}</div>
                    </div>
                </div>
            )}
        </Container>
    );
};

export default UserDashboard;