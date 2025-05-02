import React, { useState } from 'react';
import { PaymentElement, useStripe, useElements } from '@stripe/react-stripe-js';

const CheckoutForm = ({ clientSecret }) => {
    const stripe = useStripe();
    const elements = useElements();
    const [message, setMessage] = useState(null);
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        if (!stripe || !elements) {
            setMessage('Stripe.js has not loaded yet.');
            setLoading(false);
            return;
        }

        const { error, paymentIntent } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: window.location.href, // Handle post-payment redirection
            },
        });

        if (error) {
            setMessage(error.message);
        } else if (paymentIntent.status === 'succeeded') {
            setMessage('Payment successful!');
        } else {
            setMessage(`Payment failed with status: ${paymentIntent.status}`);
        }

        setLoading(false);
    };

    return (
        <form onSubmit={handleSubmit}>
            <PaymentElement />
            <button
                type="submit"
                disabled={!stripe || loading}
                style={{
                    marginTop: '20px',
                    padding: '10px 20px',
                    backgroundColor: '#5469d4',
                    color: '#fff',
                    border: 'none',
                    borderRadius: '4px',
                    cursor: 'pointer',
                }}
            >
                {loading ? 'Processing...' : 'Pay now'}
            </button>
            {message && <div style={{ marginTop: '20px', color: 'red' }}>{message}</div>}
        </form>
    );
};

export default CheckoutForm;
