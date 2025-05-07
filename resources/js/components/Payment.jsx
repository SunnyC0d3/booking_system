import React, {useEffect, useState} from 'react';
import CheckoutForm from './CheckoutForm';
import axios from 'axios';
import {loadStripe} from "@stripe/stripe-js";
import {Elements} from '@stripe/react-stripe-js';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY);
const Payment = ({orderId}) => {
    const [clientSecret, setClientSecret] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        axios.post('/api/admin/payments/stripe/create', {order_id: orderId})
            .then(response => {
                setClientSecret(response.data.data.client_secret);
            })
            .catch(err => {
                setError('Failed to retrieve payment info, look at network tab for more info.');
                console.error('Error fetching client secret:', err);
            });
    }, [orderId]);

    if (error) {
        return <div>Error: {error}</div>;
    }

    if (!clientSecret) {
        return <div>Loading payment...</div>;
    }

    const options = {
        clientSecret: clientSecret
    };

    return (
        <Elements stripe={stripePromise} options={options}>
            <CheckoutForm />
        </Elements>
    );
};

export default Payment;
