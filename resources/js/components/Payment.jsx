import React, { useEffect, useState } from 'react';
import CheckoutForm from './CheckoutForm';
import axios from 'axios';
import { loadStripe } from "@stripe/stripe-js";
import { Elements } from '@stripe/react-stripe-js';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY);

const PaymentStatusMessage = ({ status }) => {
    if (status === 'succeeded') {
        return <div style={{ color: 'green' }}>✅ Payment was successful!</div>;
    } else if (status === 'canceled') {
        return <div style={{ color: 'red' }}>❌ Payment was canceled or failed.</div>;
    } else if (status === 'processing') {
        return <div>⏳ Payment is processing, please wait...</div>;
    }
    return null;
};

const Payment = ({ orderId }) => {
    const [clientSecret, setClientSecret] = useState(null);
    const [paymentStatus, setPaymentStatus] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchPaymentIntent = async () => {
            try {
                const response = await axios.post('/api/admin/payments/stripe/create', { order_id: orderId });
                const secret = response.data.data.client_secret;

                if (secret) {
                    const stripe = await stripePromise;
                    const { paymentIntent } = await stripe.retrievePaymentIntent(secret);

                    if (paymentIntent.status === 'requires_payment_method' || paymentIntent.status === 'requires_confirmation') {
                        setClientSecret(secret);
                        console.log('1. here');
                    } else {
                        setPaymentStatus(paymentIntent.status);
                        console.log('2. here');
                    }
                } else {
                    setPaymentStatus('succeeded');
                    console.log('No client_secret returned; assuming payment completed.');
                }
            } catch (err) {
                setError('Failed to retrieve payment info, check network tab for more info.');
                console.error('Error fetching client secret:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchPaymentIntent();
    }, [orderId]);

    if (error) return <div>Error: {error}</div>;
    if (loading) return <div>Loading payment...</div>;

    if (paymentStatus) {
        return <PaymentStatusMessage status={paymentStatus} />;
    }

    if (clientSecret) {
        const options = { clientSecret };
        return (
            <Elements stripe={stripePromise} options={options}>
                <CheckoutForm />
            </Elements>
        );
    }

    return <div>Unknown payment state.</div>;
};

export default Payment;
