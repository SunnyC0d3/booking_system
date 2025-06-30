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
    const [orderReturn, setOrderReturn] = useState(item.order_return || null);
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

            setOrderReturn(res.data);
            setSuccess(true);
            setShowForm(false);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    // Helper function to safely get status name
    const getStatusName = (statusObj) => {
        if (typeof statusObj === 'string') return statusObj;
        if (statusObj && typeof statusObj === 'object') return statusObj.name || 'Unknown';
        return 'Unknown';
    };

    return (
        <div className="border-t pt-3 mt-3">
            <p><strong>Name:</strong> {item.product?.name || 'Unknown Product'}</p>
            <p><strong>Qty:</strong> {item.quantity}</p>
            <p><strong>Price:</strong> {item.price_formatted || `£${(item.price / 100).toFixed(2)}`}</p>
            <p><strong>Line Total:</strong> {item.line_total_formatted || `£${(item.line_total / 100).toFixed(2)}`}</p>

            {orderReturn ? (
                <div className="mt-2 bg-gray-100 p-3 rounded">
                    <p><strong>Return Status:</strong> <span className="text-indigo-600">{getStatusName(orderReturn.status)}</span></p>
                    <p><strong>Reason:</strong> {orderReturn.reason}</p>
                    <p className="text-sm text-gray-500">Submitted on: {new Date(orderReturn.created_at).toLocaleString()}</p>

                    {orderReturn.has_refunds && (
                        <div className="mt-2 text-sm">
                            <p><strong>Total Refunded:</strong> <span className="text-green-600">{orderReturn.total_refunded_amount_formatted}</span></p>
                        </div>
                    )}

                    {orderReturn.order_refunds && orderReturn.order_refunds.length > 0 && (
                        <div className="mt-2">
                            <p className="text-sm font-medium">Refunds:</p>
                            {orderReturn.order_refunds.map(refund => (
                                <div key={refund.id} className="text-sm text-gray-600 ml-2">
                                    {refund.amount_formatted} - {getStatusName(refund.status)}
                                    {refund.processed_at && ` on ${new Date(refund.processed_at).toLocaleDateString()}`}
                                </div>
                            ))}
                        </div>
                    )}
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

                            {success && (
                                <div className="max-w-full bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded-md">
                                    <p className="font-semibold">Return request submitted successfully!</p>
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
                                    className="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
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
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (activeTab === 'Orders') fetchOrders();
    }, [activeTab]);

    const fetchOrders = async () => {
        setLoading(true);
        setError(null);

        try {
            const res = await callApi({
                path: '/api/orders',
                authType: 'auth',
                data: {
                    user_id: user['id']
                }
            });

            console.log('Orders data:', res.data);
            setOrders(res.data);
        } catch (err) {
            setError(err.message);
            console.error('Failed to load orders', err);
        } finally {
            setLoading(false);
        }
    };

    // Helper function to safely get status name
    const getStatusName = (statusObj) => {
        if (typeof statusObj === 'string') return statusObj;
        if (statusObj && typeof statusObj === 'object') return statusObj.name || 'Unknown';
        return 'Unknown';
    };

    // Helper function to safely get role name
    const getRoleName = (roleObj) => {
        if (typeof roleObj === 'string') return roleObj;
        if (roleObj && typeof roleObj === 'object') return roleObj.name || 'Unknown';
        return 'No role assigned';
    };

    const renderContent = () => {
        switch (activeTab) {
            case 'Profile':
                return (
                    <div>
                        <h2 className="text-xl font-bold mb-4">Personal Info</h2>
                        <div className="space-y-2">
                            <p><strong>Name:</strong> {user.name || 'Not provided'}</p>
                            <p><strong>Email:</strong> {user.email}</p>
                            <p><strong>Role:</strong> {getRoleName(user.role)}</p>
                            {user.email_verified_at ? (
                                <p><strong>Email Status:</strong> <span className="text-green-600">Verified</span></p>
                            ) : (
                                <p><strong>Email Status:</strong> <span className="text-red-600">Not verified</span></p>
                            )}
                            {user.last_login_at && (
                                <p><strong>Last Login:</strong> {new Date(user.last_login_at).toLocaleString()}</p>
                            )}
                        </div>

                        {user.user_address && (
                            <div className="mt-6">
                                <h3 className="text-lg font-semibold mb-2">Address</h3>
                                <div className="text-sm space-y-1">
                                    <p>{user.user_address.address_line1}</p>
                                    {user.user_address.address_line2 && <p>{user.user_address.address_line2}</p>}
                                    <p>{user.user_address.city}, {user.user_address.state}</p>
                                    <p>{user.user_address.postal_code}</p>
                                    <p>{user.user_address.country}</p>
                                </div>
                            </div>
                        )}
                    </div>
                );
            case 'Orders':
                return (
                    <div>
                        <h2 className="text-xl font-bold mb-4">Your Orders</h2>

                        {loading && (
                            <div className="text-center py-4">
                                <p>Loading orders...</p>
                            </div>
                        )}

                        {!loading && orders.length === 0 && (
                            <p className="text-gray-500">No orders yet.</p>
                        )}

                        {!loading && orders.length > 0 && (
                            <ul className="space-y-4">
                                {orders.map(order => (
                                    <li key={order.id} className="border p-4 rounded bg-white shadow-sm">
                                        <div className="border-b pb-3 mb-3">
                                            <p><strong>Order #</strong> {order.id}</p>
                                            <p><strong>Status:</strong> <span className="text-indigo-600">{getStatusName(order.status)}</span></p>
                                            <p><strong>Date:</strong> {new Date(order.created_at).toLocaleDateString()}</p>
                                            <p><strong>Total:</strong> {order.total_amount_formatted || `£${(order.total_amount / 100).toFixed(2)}`}</p>
                                        </div>

                                        <div className="space-y-3">
                                            <h4 className="font-medium">Items:</h4>
                                            {order.order_items && order.order_items.length > 0 ? (
                                                order.order_items.map(item => (
                                                    <OrderItemWithReturn
                                                        key={item.id}
                                                        item={item}
                                                        orderStatus={getStatusName(order.status)}
                                                    />
                                                ))
                                            ) : (
                                                <p className="text-gray-500">No items found for this order.</p>
                                            )}
                                        </div>

                                        {order.payments && order.payments.length > 0 && (
                                            <div className="mt-4 pt-3 border-t">
                                                <p className="text-sm"><strong>Payment Status:</strong> {getStatusName(order.payments[0]?.status)}</p>
                                            </div>
                                        )}
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