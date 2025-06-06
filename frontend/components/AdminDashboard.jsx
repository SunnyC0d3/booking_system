import React, { useEffect, useState } from 'react';
import Container from '@components/Wrapper/Container';
import callApi from '@api/callApi';
import ErrorMessage from '@components/ErrorMessage';

const TABS = ['Returns', 'Orders', 'Payments', 'Refunds'];

const AdminDashboard = () => {
    const [activeTab, setActiveTab] = useState('Returns');
    const [returns, setReturns] = useState([]);
    const [orders, setOrders] = useState([]);
    const [payments, setPayments] = useState([]);
    const [refunds, setRefunds] = useState([]);
    const [error, setError] = useState(null);

    useEffect(() => {
        switch (activeTab) {
            case 'Returns': fetchReturns(); break;
            case 'Orders': fetchOrders(); break;
            case 'Payments': fetchPayments(); break;
            case 'Refunds': fetchRefunds(); break;
        }
    }, [activeTab]);

    const fetchReturns = async () => {
        try {
            const res = await callApi({ path: '/api/admin/returns', authType: 'auth' });
            setReturns(res.data);
        } catch (err) {
            setError(err.message);
        }
    };

    const fetchOrders = async () => {
        try {
            const res = await callApi({ path: '/api/admin/orders', authType: 'auth' });
            setOrders(res.data);
        } catch (err) {
            setError(err.message);
        }
    };

    const fetchPayments = async () => {
        try {
            const res = await callApi({ path: '/api/admin/payments', authType: 'auth' });
            setPayments(res.data);
        } catch (err) {
            setError(err.message);
        }
    };

    const fetchRefunds = async () => {
        try {
            const res = await callApi({ path: '/api/admin/refunds', authType: 'auth' });
            setRefunds(res.data);
        } catch (err) {
            setError(err.message);
        }
    };

    const handleReturnAction = async (returnId, action) => {
        try {
            await callApi({
                path: `/api/admin/returns/${returnId}/${action}`,
                method: 'POST',
                authType: 'auth',
            });
            fetchReturns(); // Refresh list
        } catch (err) {
            alert(`Failed to ${action} return.`);
        }
    };

    const renderReturns = () => (
        <div>
            <h2 className="text-xl font-semibold mb-4">Return Requests</h2>
            {returns.length === 0 ? (
                <p>No return requests.</p>
            ) : (
                returns.map(ret => (
                    <div key={ret.id} className="border p-4 mb-4 rounded bg-white shadow">
                        <p><strong>Reason:</strong> {ret.reason}</p>
                        <p><strong>Status:</strong> {ret.status.name}</p>
                        <p><strong>Product:</strong> {ret.order_item.product.name}</p>
                        <p className="text-sm text-gray-500">Submitted: {new Date(ret.created_at).toLocaleString()}</p>
                        {ret.status.name === 'Requested' && (
                            <div className="mt-2 flex gap-2">
                                <button
                                    onClick={() => handleReturnAction(ret.id, 'approve')}
                                    className="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm"
                                >
                                    Approve
                                </button>
                                <button
                                    onClick={() => handleReturnAction(ret.id, 'deny')}
                                    className="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm"
                                >
                                    Deny
                                </button>
                            </div>
                        )}
                    </div>
                ))
            )}
        </div>
    );

    const renderOrders = () => (
        <div>
            <h2 className="text-xl font-semibold mb-4">All Orders</h2>
            {orders.length === 0 ? (
                <p>No orders found.</p>
            ) : (
                orders.map(order => (
                    <div key={order.id} className="border p-4 mb-3 rounded bg-white shadow">
                        <p><strong>Order #</strong> {order.id}</p>
                        <p><strong>User:</strong> {order.user.email}</p>
                        <p><strong>Status:</strong> {order.status.name}</p>
                        <p><strong>Total:</strong> £{(order.total_amount / 100).toFixed(2)}</p>
                        <p><strong>Date:</strong> {new Date(order.created_at).toLocaleDateString()}</p>
                    </div>
                ))
            )}
        </div>
    );

    const renderPayments = () => (
        <div>
            <h2 className="text-xl font-semibold mb-4">Payments</h2>
            {payments.length === 0 ? (
                <p>No payments recorded.</p>
            ) : (
                payments.map(pay => (
                    <div key={pay.id} className="border p-4 mb-3 rounded bg-white shadow">
                        <p><strong>Order ID:</strong> {pay.order_id}</p>
                        <p><strong>Amount:</strong> £{(pay.amount / 100).toFixed(2)}</p>
                        <p><strong>Status:</strong> {pay.status}</p>
                        <p><strong>Method:</strong> {pay.gateway}</p>
                        <p><strong>Date:</strong> {new Date(pay.created_at).toLocaleString()}</p>
                    </div>
                ))
            )}
        </div>
    );

    const renderRefunds = () => (
        <div>
            <h2 className="text-xl font-semibold mb-4">Refunds</h2>
            {refunds.length === 0 ? (
                <p>No refunds issued.</p>
            ) : (
                refunds.map(refund => (
                    <div key={refund.id} className="border p-4 mb-3 rounded bg-white shadow">
                        <p><strong>Return ID:</strong> {refund.order_return_id}</p>
                        <p><strong>Amount:</strong> £{(refund.amount / 100).toFixed(2)}</p>
                        <p><strong>Status:</strong> {refund.status?.name || '—'}</p>
                        <p><strong>Notes:</strong> {refund.notes || 'N/A'}</p>
                        <p><strong>Processed:</strong> {refund.processed_at ? new Date(refund.processed_at).toLocaleString() : 'Pending'}</p>
                    </div>
                ))
            )}
        </div>
    );

    const renderContent = () => {
        if (error) return <ErrorMessage message={error} />;

        switch (activeTab) {
            case 'Returns': return renderReturns();
            case 'Orders': return renderOrders();
            case 'Payments': return renderPayments();
            case 'Refunds': return renderRefunds();
            default: return null;
        }
    };

    return (
        <Container>
            <div className="min-h-screen bg-gray-100 py-10">
                <div className="max-w-6xl mx-auto bg-white p-6 rounded shadow">
                    <h1 className="text-2xl font-bold mb-6">Admin Dashboard</h1>
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
        </Container>
    );
};

export default AdminDashboard;
