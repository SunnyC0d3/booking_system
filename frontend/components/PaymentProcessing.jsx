import React from 'react';

const PaymentProcessing = () => (
    <div className="min-h-screen flex items-center justify-center">
        <div className="bg-white max-w-xl mx-auto p-8 rounded-lg shadow-md border border-gray-200 text-center space-y-4">
            <div className="text-5xl">⏳</div>
            <h2 className="text-2xl font-semibold text-blue-600">Processing Payment</h2>
            <p className="text-gray-700">Your payment is currently being processed. This may take a few moments—please don’t refresh.</p>
        </div>
    </div>
);

export default PaymentProcessing;
