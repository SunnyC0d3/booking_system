import React, { useEffect, useState } from 'react';
import CheckoutForm from './CheckoutForm';
import axios from 'axios';

const Payment = ({ orderId }) => {
    const [clientSecret, setClientSecret] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        axios.post('/api/admin/payments/stripe/create', { order_id: orderId })
            .then(response => {
                setClientSecret(response.data.client_secret);
            })
            .catch(err => {
                setError('Failed to retrieve payment info');
                console.error('Error fetching client secret:', err);
            });
    }, [orderId]);

    if (error) {
        return <div>Error: {error}</div>;
    }

    if (!clientSecret) {
        return <div>Loading payment...</div>;
    }

    return <CheckoutForm clientSecret={clientSecret} />;
};

export default Payment;
