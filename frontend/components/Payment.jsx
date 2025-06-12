import React, {useEffect, useState, useRef} from 'react';
import {useLocation, useParams, useNavigate} from 'react-router-dom';
import {loadStripe} from '@stripe/stripe-js';
import {Elements} from '@stripe/react-stripe-js';
import CheckoutForm from '@components/CheckoutForm';
import ErrorMessage from '@components/ErrorMessage';
import Container from '@components/Wrapper/Container';
import callApi from '@api/callApi';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY);

const Payment = () => {
    const {orderId} = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const orderItems = location.state?.orderItems || [];
    const [clientSecret, setClientSecret] = useState(null);
    const [paymentStatus, setPaymentStatus] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);

    const hasFetched = useRef(false);

    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const paymentIntentId = urlParams.get('payment_intent');
        const paymentIntentClientSecret = urlParams.get('payment_intent_client_secret');

        if (paymentIntentId && paymentIntentClientSecret) {
            checkPaymentStatus(paymentIntentClientSecret);
            return;
        }

        if (hasFetched.current) return;

        fetchPaymentIntent();
    }, [orderId]);

    const checkPaymentStatus = async (clientSecret) => {
        try {
            const stripe = await stripePromise;
            const {paymentIntent} = await stripe.retrievePaymentIntent(clientSecret);

            console.log('Payment Intent Status:', paymentIntent.status);

            if (paymentIntent.status === 'succeeded') {
                await verifyPaymentWithBackend(paymentIntent.id);
                setPaymentStatus('succeeded');
            } else {
                setPaymentStatus(paymentIntent.status);
            }
        } catch (err) {
            console.error('Error checking payment status:', err);
            setError('Failed to verify payment status.');
        } finally {
            setLoading(false);
        }
    };

    const verifyPaymentWithBackend = async (paymentIntentId) => {
        try {
            await callApi({
                method: 'POST',
                path: '/api/payments/stripe/verify',
                authType: 'client',
                data: {
                    payment_intent_id: paymentIntentId,
                    order_id: orderId
                }
            });
        } catch (err) {
            console.error('Backend verification failed:', err);
        }
    };

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

                console.log('Initial Payment Intent Status:', paymentIntent.status);

                if (paymentIntent.status === 'requires_payment_method' ||
                    paymentIntent.status === 'requires_confirmation') {
                    setClientSecret(secret);
                } else if (paymentIntent.status === 'succeeded') {
                    setPaymentStatus('succeeded');
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
            hasFetched.current = false;
        } finally {
            setLoading(false);
        }
    };

    if (loading) return <div>Loading payment...</div>;

    if (paymentStatus === 'succeeded') {
        return (
            <Container>
                <div className="text-center py-8">
                    <h2 className="text-2xl font-bold text-green-600 mb-4">Payment Successful!</h2>
                    <p className="text-gray-600 mb-4">Your order has been confirmed.</p>
                    <button
                        onClick={() => navigate('/orders')}
                        className="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700"
                    >
                        View Orders
                    </button>
                </div>
            </Container>
        );
    }

    if (paymentStatus === 'canceled') {
        return (
            <Container>
                <div className="text-center py-8">
                    <h2 className="text-2xl font-bold text-red-600 mb-4">Payment Canceled</h2>
                    <p className="text-gray-600 mb-4">Your payment was canceled.</p>
                    <button
                        onClick={() => window.location.reload()}
                        className="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700"
                    >
                        Try Again
                    </button>
                </div>
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
                        <CheckoutForm
                            orderItems={orderItems}
                            orderId={orderId}
                            onPaymentSuccess={() => setPaymentStatus('succeeded')}
                        />
                    </Elements>
                )}
            </Container>
        );
    }

    return (
        <Container>
            <ErrorMessage message={error || "Unknown payment state."}/>
        </Container>
    );
};

export default Payment;