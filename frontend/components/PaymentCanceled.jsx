import React from 'react';

const PaymentCanceled = () => (
    <div className="bg-white max-w-xl mx-auto p-8 rounded-lg shadow-md border border-gray-200 text-center space-y-4">
        <div className="text-5xl">❌</div>
        <h2 className="text-2xl font-semibold text-red-600">Payment Canceled</h2>
        <p className="text-gray-700">Your payment was canceled or failed. Please try again or contact support if needed.</p>
    </div>
);

export default PaymentCanceled;
