import React, {useEffect, useState, useRef, lazy} from 'react';
import {useLocation, useParams} from 'react-router-dom';
import {loadStripe} from '@stripe/stripe-js';
import {Elements} from '@stripe/react-stripe-js';
import CheckoutForm from '@components/CheckoutForm';
import ErrorMessage from '@components/ErrorMessage';
import Container from '@components/Wrapper/Container';
import callApi from '@api/callApi';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY);

const PaymentSuccess = lazy(() => import('@components/PaymentSuccess'));
const PaymentCanceled = lazy(() => import('@components/PaymentCanceled'));
const PaymentProcessing = lazy(() => import('@components/PaymentProcessing'));

const PaymentStatusMessage = ({status}) => {
    switch (status) {
        case 'succeeded':
            return <PaymentSuccess />;
        case 'canceled':
            return <PaymentCanceled />;
        case 'processing':
            return <PaymentProcessing />;
        default:
            return null;
    }
};

const Payment = () => {
    const {orderId} = useParams();
    const location = useLocation();
    const orderItems = location.state?.orderItems || [];
    const [clientSecret, setClientSecret] = useState(null);
    const [paymentStatus, setPaymentStatus] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);

    // Prevent duplicate API calls
    const hasFetched = useRef(false);

    useEffect(() => {
        // Prevent duplicate calls in StrictMode or component re-renders
        if (hasFetched.current) return;

        const fetchPaymentIntent = async () => {
            hasFetched.current = true;

            try {
                const response = await callApi({
                    method: 'POST',
                    path: '/api/payments/stripe/create',
                    authType: 'client',
                    data: {
                        order_id: orderId
                    }
                });

                const secret = response.data.client_secret;

                if (secret) {
                    const stripe = await stripePromise;
                    const {paymentIntent} = await stripe.retrievePaymentIntent(secret);

                    if (paymentIntent.status === 'requires_payment_method' || paymentIntent.status === 'requires_confirmation') {
                        setClientSecret(secret);
                    } else {
                        setPaymentStatus(paymentIntent.status);
                    }
                } else {
                    setPaymentStatus('succeeded');
                    console.log('No client_secret returned; assuming payment completed.');
                }
            } catch (err) {
                setError('Failed to retrieve payment info.');
                console.error('Error fetching client secret:', err);
                // Reset the flag on error so user can retry
                hasFetched.current = false;
            } finally {
                setLoading(false);
            }
        };

        fetchPaymentIntent();
    }, [orderId]);

    if (loading) return <div>Loading payment...</div>;

    if (paymentStatus) {
        return (
            <Container>
                <PaymentStatusMessage status={paymentStatus}/>
            </Container>
        );
    }

    if (clientSecret) {
        const options = {clientSecret};
        return (
            <Container>
                {error && <ErrorMessage message={error}/>}
                {!error && (
                    <Elements stripe={stripePromise} options={options}>
                        <CheckoutForm orderItems={orderItems}/>
                    </Elements>
                )}
            </Container>
        );
    }

    return (
        <Container>
            <ErrorMessage message={"Unknown payment state."}/>
        </Container>
    );
};

export default Payment;