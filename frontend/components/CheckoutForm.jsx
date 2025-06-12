import React, {useState} from 'react';
import {PaymentElement, useStripe, useElements} from '@stripe/react-stripe-js';

const CheckoutForm = ({orderItems, orderId, onPaymentSuccess}) => {
    const stripe = useStripe();
    const elements = useElements();
    const [message, setMessage] = useState(null);
    const [loading, setLoading] = useState(false);

    const [delivery, setDelivery] = useState({
        name: '',
        email: '',
        address: '',
        city: '',
        postcode: '',
    });

    const [billing, setBilling] = useState({
        name: '',
        email: '',
        address: '',
        city: '',
        postcode: '',
    });

    const [sameAsDelivery, setSameAsDelivery] = useState(true);

    const handleDeliveryChange = (e) => {
        const updated = {...delivery, [e.target.name]: e.target.value};
        setDelivery(updated);
        if (sameAsDelivery) setBilling(updated);
    };

    const handleBillingChange = (e) => {
        setBilling({...billing, [e.target.name]: e.target.value});
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setMessage(null);

        if (!stripe || !elements) {
            setMessage('Stripe.js has not loaded yet.');
            setLoading(false);
            return;
        }

        const billingDetails = {
            name: billing.name,
            email: billing.email,
            address: {
                line1: billing.address,
                city: billing.city,
                postal_code: billing.postcode,
                country: 'GB'
            }
        };

        try {
            const {error} = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: `${window.location.origin}/payment/${orderId}?return=true`,
                    payment_method_data: {
                        billing_details: billingDetails
                    }
                },
                redirect: 'if_required'
            });

            if (error) {
                console.error('Payment confirmation error:', error);
                if (error.type === 'card_error' || error.type === 'validation_error') {
                    setMessage(error.message);
                } else {
                    setMessage('An unexpected error occurred.');
                }
            } else {
                // Payment succeeded without redirect
                console.log('Payment succeeded!');
                setMessage('Payment successful! Processing...');

                // Call the success callback after a short delay to allow webhook processing
                setTimeout(() => {
                    if (onPaymentSuccess) {
                        onPaymentSuccess();
                    }
                }, 2000);
            }
        } catch (err) {
            console.error('Unexpected error during payment:', err);
            setMessage('An unexpected error occurred during payment.');
        }

        setLoading(false);
    };

    const Input = ({label, name, value, onChange, placeholder}) => (
        <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            <input
                type="text"
                name={name}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                required
                className="w-full border border-gray-300 rounded-md px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
        </div>
    );

    return (
        <form onSubmit={handleSubmit}
              className="max-w-4xl w-full mx-auto bg-white rounded-2xl shadow-md p-6 mt-10 mb-12">
            <h2 className="text-2xl font-semibold text-gray-800">Checkout</h2>

            <div className="space-y-2">
                <h3 className="text-gray-700 font-medium">Order Summary</h3>
                <ul className="text-sm divide-y divide-gray-200">
                    {orderItems && orderItems.length > 0 ? (
                        <>
                            {orderItems.map((item, index) => (
                                <li key={index} className="flex justify-between py-2">
                                    <span>{item.name} × {item.quantity}</span>
                                    <span className="font-medium">£{(item.price)}</span>
                                </li>
                            ))}
                            <li className="flex justify-between py-2 font-semibold">
                                <span>Total</span>
                                <span>£{orderItems.reduce((sum, item) => sum + (item.price * item.quantity), 0)}</span>
                            </li>
                        </>
                    ) : (
                        <li className="text-gray-500">No items in your order.</li>
                    )}
                </ul>
            </div>

            <div>
                <h3 className="text-gray-700 font-medium mb-2">Delivery Address</h3>
                <Input label="Full Name" name="name" value={delivery.name} onChange={handleDeliveryChange}
                       placeholder="Jane Doe"/>
                <Input label="E-mail address" name="email" value={delivery.email} onChange={handleDeliveryChange}
                       placeholder="johndoe@gmail.com"/>
                <Input label="Address" name="address" value={delivery.address} onChange={handleDeliveryChange}
                       placeholder="123 Main St"/>
                <Input label="City" name="city" value={delivery.city} onChange={handleDeliveryChange}
                       placeholder="London"/>
                <Input label="Postcode" name="postcode" value={delivery.postcode} onChange={handleDeliveryChange}
                       placeholder="E1 6AN"/>
            </div>

            <div className="flex items-center">
                <input type="checkbox" id="sameAsDelivery" checked={sameAsDelivery}
                       onChange={(e) => setSameAsDelivery(e.target.checked)} className="mr-2"/>
                <label htmlFor="sameAsDelivery" className="text-sm text-gray-700">Billing address same as
                    delivery</label>
            </div>

            {!sameAsDelivery && (
                <div>
                    <h3 className="text-gray-700 font-medium mb-2">Billing Address</h3>
                    <Input label="Full Name" name="name" value={billing.name} onChange={handleBillingChange}
                           placeholder="Jane Doe"/>
                    <Input label="E-mail address" name="email" value={billing.email} onChange={handleBillingChange}
                           placeholder="johndoe@gmail.com"/>
                    <Input label="Address" name="address" value={billing.address} onChange={handleBillingChange}
                           placeholder="456 Billing Rd"/>
                    <Input label="City" name="city" value={billing.city} onChange={handleBillingChange}
                           placeholder="Birmingham"/>
                    <Input label="Postcode" name="postcode" value={billing.postcode} onChange={handleBillingChange}
                           placeholder="B1 1AA"/>
                </div>
            )}

            <div>
                <h3 className="text-gray-700 font-medium mb-2">Payment</h3>
                <div className="p-4 border border-gray-300 rounded-md">
                    <PaymentElement/>
                </div>
            </div>

            <button
                type="submit"
                disabled={!stripe || loading}
                className={`w-full py-3 px-6 text-sm font-semibold rounded-md transition-colors duration-200
                    ${loading ? 'bg-indigo-300 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'} text-white`}
            >
                {loading ? 'Processing...' : 'Pay Now'}
            </button>

            {message && (
                <div className={`text-center text-sm mt-4 ${
                    message.includes('successful') ? 'text-green-600' : 'text-red-600'
                }`}>
                    {message}
                </div>
            )}
        </form>
    );
};

export default CheckoutForm;