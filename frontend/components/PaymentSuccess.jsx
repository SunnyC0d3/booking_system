import React from 'react';

const PaymentSuccess = () => (
    <div className="min-h-screen flex items-center justify-center">
        <div className="bg-white max-w-xl mx-auto p-8 rounded-lg shadow-md border border-gray-200 text-center space-y-4">
            <div className="text-5xl">🎉</div>
            <h2 className="text-2xl font-semibold text-green-600">Payment Successful</h2>
            <p className="text-gray-700">Thank you for your purchase! Your payment has been processed successfully.</p>
        </div>
    </div>
);

export default PaymentSuccess;
