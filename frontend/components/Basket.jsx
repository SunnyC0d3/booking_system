import React from 'react';
import {useNavigate} from 'react-router-dom';
import {useBasket} from '@context/BasketContext';
import Container from '@components/Wrapper/Container';

const Basket = () => {
    const {basket} = useBasket();
    const navigate = useNavigate();

    const handleCheckout = () => {
        const orderId = '1';
        navigate(`/payment/${orderId}`, {state: {orderItems: basket}});
    };

    const totalAmount = basket.reduce((sum, item) => sum + item.price * item.quantity, 0).toFixed(2);

    return (
        <Container>
            <div className="max-w-4xl w-full mx-auto bg-white rounded-2xl shadow-md p-6 mt-10 mb-12">
                <h1 className="text-3xl font-bold text-gray-900 mb-6 border-b pb-4">Your Basket</h1>

                {basket.length === 0 ? (
                    <p className="text-gray-600 text-center py-10">Your basket is currently empty.</p>
                ) : (
                    <>
                        <ul className="divide-y divide-gray-200">
                            {basket.map((item) => (
                                <li key={item.id} className="flex justify-between items-center py-6">
                                    <div className="flex items-center gap-4">
                                        <img
                                            src={item.thumbnail}
                                            alt={item.name}
                                            className="w-24 h-24 rounded-lg object-cover border"
                                        />
                                        <div>
                                            <h2 className="text-lg font-medium text-gray-800">{item.name}</h2>
                                            <p className="text-sm text-gray-500">Quantity: {item.quantity}</p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-lg font-semibold text-indigo-600">
                                            £{(item.price * item.quantity).toFixed(2)}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div
                            className="flex flex-col sm:flex-row justify-between items-center border-t pt-6 mt-6 gap-4">
                            <p className="text-xl font-bold text-gray-800">
                                Total: <span className="text-indigo-600">£{totalAmount}</span>
                            </p>
                            <button
                                onClick={handleCheckout}
                                className="bg-indigo-600 text-white px-6 py-3 rounded-xl shadow-sm hover:bg-indigo-700 transition"
                            >
                                Proceed to Payment
                            </button>
                        </div>
                    </>
                )}
            </div>
        </Container>
    );
};

export default Basket;
