import React, {useEffect, useState} from 'react';
import Container from '@components/Wrapper/Container';
import callApi from '@api/callApi';
import ErrorMessage from '@components/ErrorMessage';

const TABS = ['Returns', 'Refunds', 'Orders', 'Payments'];

const AdminDashboard = () => {
    const [activeTab, setActiveTab] = useState('Returns');
    const [returns, setReturns] = useState([]);
    const [orders, setOrders] = useState([]);
    const [payments, setPayments] = useState([]);
    const [refunds, setRefunds] = useState([]);
    const [error, setError] = useState(null);

    useEffect(() => {
        switch (activeTab) {
            case 'Returns':
                fetchReturns();
                break;
            case 'Orders':
                fetchOrders();
                break;
            case 'Payments':
                fetchPayments();
                break;
            case 'Refunds':
                fetchRefunds();
                break;
        }
    }, [activeTab]);

    const fetchReturns = async () => {
        try {
            const res = await callApi({
                path: '/api/admin/returns',
                authType: 'auth'
            });

            setReturns(res.data);
        } catch (err) {
            setError(err.message);
        }
    };

    const fetchOrders = async () => {
        try {
            const res = await callApi({
                path: '/api/admin/orders',
                authType: 'auth'
            });

            setOrders(res.data);
        } catch (err) {
            setError(err.message);
        }
    };

    const fetchPayments = async () => {
        try {
            const res = await callApi({
                path: '/api/admin/payments',
                authType: 'auth'
            });

            setPayments(res.data);
        } catch (err) {
            setError(err.message);
        }
    };

    const fetchRefunds = async () => {
        try {
            const res = await callApi({
                path: '/api/admin/refunds',
                authType: 'auth'
            });

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

            fetchReturns();
        } catch (err) {
            alert(`Failed to ${action} return.`);
        }
    };

    const handleRefund = async (ret) => {
        try {
            const order = ret?.order_item?.order;
            const payments = order?.payments;

            if (!payments || payments.length === 0) {
                alert("No payment found to refund.");
                return;
            }

            const paymentMethod = payments[0]?.payment_method?.name?.toLowerCase() || 'stripe';

            const res = await callApi({
                path: `/api/admin/refunds/${paymentMethod}/${ret.id}`,
                method: 'POST',
                authType: 'auth',
            });

            alert("Refund processed successfully.");

            fetchReturns();
        } catch (err) {
            alert("Refund failed.");
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
                        <p><strong>Return ID:</strong> {ret.id}</p>
                        <p><strong>Reason:</strong> {ret.reason}</p>
                        <p><strong>Status:</strong> <span className="text-indigo-600">{ret.status.name}</span></p>
                        <p><strong>Product:</strong> {ret.order_item?.product?.name || 'N/A'}</p>
                        <p><strong>Customer:</strong> {ret.order_item?.order?.user?.email || 'N/A'}</p>
                        <p><strong>Order ID:</strong> {ret.order_item?.order?.id || 'N/A'}</p>
                        <p><strong>Item Price:</strong> £{ret.order_item?.price ? (ret.order_item.price / 100).toFixed(2) : '0.00'}</p>
                        <p><strong>Quantity:</strong> {ret.order_item?.quantity || 'N/A'}</p>
                        <p><strong>Total Item Value:</strong> £{ret.order_item?.price && ret.order_item?.quantity ? ((ret.order_item.price * ret.order_item.quantity) / 100).toFixed(2) : '0.00'}</p>
                        <p className="text-sm text-gray-500">Submitted: {new Date(ret.created_at).toLocaleString()}</p>

                        {(ret.order_return_status?.name === 'Requested' || ret.status === 'Requested') && (
                            <div className="mt-2 flex gap-2">
                                <button
                                    onClick={() => handleReturnAction(ret.id, 'approve')}
                                    className="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm"
                                >
                                    Approve
                                </button>
                                <button
                                    onClick={() => handleReturnAction(ret.id, 'reject')}
                                    className="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm"
                                >
                                    Deny
                                </button>
                            </div>
                        )}

                        {(ret.order_return_status?.name === 'Approved' || ret.status === 'Approved') && (
                            <div className="mt-2">
                                <button
                                    onClick={() => handleRefund(ret)}
                                    className="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm"
                                >
                                    Process Refund
                                </button>
                            </div>
                        )}

                        {ret.order_refunds && ret.order_refunds.length > 0 && (
                            <div className="mt-3 bg-gray-50 p-3 rounded">
                                <strong>Refunds:</strong>
                                {ret.order_refunds.map(refund => (
                                    <div key={refund.id} className="text-sm mt-1">
                                        £{(refund.amount / 100).toFixed(2)} - {refund.order_refund_status?.name || refund.status || 'Unknown'}
                                        {refund.processed_at && ` on ${new Date(refund.processed_at).toLocaleDateString()}`}
                                    </div>
                                ))}
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
                        <p><strong>Customer:</strong> {order.user?.email || 'N/A'}</p>
                        <p><strong>Status:</strong> <span className="text-indigo-600">{order.status?.name || order.order_status?.name || 'Unknown'}</span></p>
                        <p><strong>Total:</strong> £{(order.total_amount / 100).toFixed(2)}</p>
                        <p><strong>Date:</strong> {new Date(order.created_at).toLocaleDateString()}</p>

                        {order.order_items && order.order_items.length > 0 && (
                            <div className="mt-2">
                                <strong>Items ({order.order_items.length}):</strong>
                                <div className="text-sm text-gray-600 ml-4">
                                    {order.order_items.map(item => (
                                        <div key={item.id}>
                                            {item.product?.name || 'Unknown Product'} - Qty: {item.quantity} - £{(item.price / 100).toFixed(2)} each
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {order.payments && order.payments.length > 0 && (
                            <div className="mt-2">
                                <strong>Payment Status:</strong> <span className="text-sm text-gray-600">{order.payments[0]?.status || 'Unknown'}</span>
                            </div>
                        )}
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
                        <p><strong>Payment ID:</strong> {pay.id}</p>
                        <p><strong>Order ID:</strong> {pay.order_id}</p>
                        <p><strong>Customer:</strong> {pay.user?.email || pay.order?.user?.email || 'N/A'}</p>
                        <p><strong>Amount:</strong> £{(pay.amount / 100).toFixed(2)}</p>
                        <p><strong>Status:</strong> <span className="text-indigo-600">{pay.status}</span></p>
                        <p><strong>Method:</strong> {pay.payment_method?.name || pay.gateway?.charAt(0).toUpperCase() + pay.gateway?.slice(1) || 'Unknown'}</p>
                        <p><strong>Transaction Ref:</strong> <span className="text-sm text-gray-600">{pay.transaction_reference || 'N/A'}</span></p>
                        <p><strong>Processed:</strong> {pay.processed_at ? new Date(pay.processed_at).toLocaleString() : 'Not processed'}</p>
                        <p><strong>Date:</strong> {new Date(pay.created_at).toLocaleString()}</p>

                        {pay.order && (
                            <div className="mt-2 text-sm text-gray-600">
                                <strong>Order Total:</strong> £{(pay.order.total_amount / 100).toFixed(2)}
                            </div>
                        )}
                    </div>
                ))
            )}
        </div>
    );

    const renderRefunds = () => (
        <div>
            <h2 className="text-xl font-semibold mb-4">Refunds</h2>
            {refunds.length === 0 ? (
                <p>No refunds recorded.</p>
            ) : (
                refunds.map(refund => (
                    <div key={refund.id} className="border p-4 mb-3 rounded bg-white shadow">
                        <p><strong>Refund ID:</strong> {refund.id}</p>
                        <p><strong>Amount:</strong> £{(refund.amount / 100).toFixed(2)}</p>
                        <p><strong>Status:</strong> <span className="text-indigo-600">{refund.order_refund_status?.name || refund.status?.name || 'Unknown'}</span></p>
                        <p><strong>Processed:</strong> {refund.processed_at ? new Date(refund.processed_at).toLocaleString() : 'Not processed'}</p>

                        {refund.order_return && (
                            <div className="mt-2">
                                <p><strong>Return ID:</strong> {refund.order_return.id}</p>
                                <p><strong>Return Reason:</strong> {refund.order_return.reason}</p>

                                {refund.order_return.order_item && (
                                    <div className="mt-1">
                                        <p><strong>Order ID:</strong> {refund.order_return.order_item.order?.id || 'N/A'}</p>
                                        <p><strong>Customer:</strong> {refund.order_return.order_item.order?.user?.email || 'N/A'}</p>
                                        <p><strong>Product:</strong> {refund.order_return.order_item.product?.name || 'N/A'}</p>
                                        <p><strong>Item Price:</strong> £{refund.order_return.order_item.price ? (refund.order_return.order_item.price / 100).toFixed(2) : '0.00'}</p>
                                        <p><strong>Quantity:</strong> {refund.order_return.order_item.quantity || 'N/A'}</p>
                                    </div>
                                )}
                            </div>
                        )}

                        {refund.notes && (
                            <div className="mt-2">
                                <p><strong>Notes:</strong> <span className="text-sm text-gray-600">{refund.notes}</span></p>
                            </div>
                        )}

                        <p className="text-sm text-gray-500 mt-2">Created: {new Date(refund.created_at).toLocaleString()}</p>
                    </div>
                ))
            )}
        </div>
    );

    const renderContent = () => {
        if (error) return <ErrorMessage message={error}/>;

        switch (activeTab) {
            case 'Returns':
                return renderReturns();
            case 'Orders':
                return renderOrders();
            case 'Payments':
                return renderPayments();
            case 'Refunds':
                return renderRefunds();
            default:
                return null;
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