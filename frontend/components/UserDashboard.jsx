import React, {useState, useEffect} from 'react';
import Container from '@components/Wrapper/Container';
import {useAuthContext} from '@context/AuthContext';
import callApi from '@api/callApi';
import ErrorMessage from '@components/ErrorMessage';

const TABS = ['Profile', 'Orders'];

const OrderItemWithReturn = ({ item }) => {
    const [showForm, setShowForm] = useState(false);
    const [reason, setReason] = useState('');
    const [status, setStatus] = useState(item.return_status || null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

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

            setStatus('pending');
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
            <p><strong>Price:</strong> £{item.product.price}</p>

            {!status && !showForm && (
                <button
                    onClick={() => setShowForm(true)}
                    className="mt-2 px-3 py-1 text-sm bg-yellow-500 text-white rounded hover:bg-yellow-600"
                >
                    Create Return
                </button>
            )}

            {showForm && (
                <div className="mt-2 space-y-2">
                    <textarea
                        className="w-full border rounded p-2"
                        rows={3}
                        value={reason}
                        onChange={e => setReason(e.target.value)}
                        placeholder="Enter return reason..."
                    />
                    <div className="flex gap-2">
                        <button
                            onClick={handleSubmit}
                            className="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                            disabled={loading}
                        >
                            {loading ? 'Submitting...' : 'Submit'}
                        </button>
                        <button
                            onClick={() => setShowForm(false)}
                            className="px-3 py-1 text-sm bg-gray-300 rounded hover:bg-gray-400"
                        >
                            Cancel
                        </button>
                    </div>
                    {error && <p className="text-red-500 text-sm">{error}</p>}
                    {success && <p className="text-green-600 text-sm">Return submitted successfully!</p>}
                </div>
            )}
        </div>
    );
};

const UserDashboard = () => {
    const {user} = useAuthContext();
    const [error, setError] = useState(null);
    const [activeTab, setActiveTab] = useState('Profile');
    const [orders, setOrders] = useState([]);
    const [returns, setReturns] = useState([]);

    useEffect(() => {
        if (activeTab === 'Orders') fetchOrders();
        if (activeTab === 'Returns') fetchReturns();
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

    const fetchReturns = async () => {
        try {
            const res = await callApi({
                path: '/api/returns',
                authType: 'auth'
            });

            setReturns(res.data.returns);
        } catch (err) {
            setError(err.message);
            console.error('Failed to load returns', err);
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
                                        <p>Status: <span className="text-sm text-indigo-600">{order.status.name}</span></p>
                                        <p>Date: {new Date(order.created_at).toLocaleDateString()}</p>
                                        <p>Total: £{(order.total_amount/100).toFixed(2)}</p>

                                        <div className="mt-4 space-y-3">
                                            {order.orderItem.map(item => (
                                                <OrderItemWithReturn key={item.id} item={item} />
                                            ))}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                );
            case 'Returns':
                return (
                    <div>
                        <h2 className="text-xl font-bold mb-4">Return Requests</h2>
                        <button
                            className="mb-4 px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600"
                            onClick={() => alert('Return form coming soon!')}
                        >
                            Create New Return
                        </button>
                        {returns.length === 0 ? (
                            <p>No return requests yet.</p>
                        ) : (
                            <ul className="space-y-3">
                                {returns.map(ret => (
                                    <li key={ret.id} className="border p-4 rounded bg-white shadow-sm">
                                        <p>Return ID: {ret.id}</p>
                                        <p>Reason: {ret.reason}</p>
                                        <p>Status: <span className="text-sm text-red-600">{ret.status}</span></p>
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
