import React from 'react';
import ReactDOM from 'react-dom/client';
import Payment from './components/Payment';

const rootElement = document.getElementById('app');
const orderId = rootElement.getAttribute('data-order-id');
const orderItems = JSON.parse(rootElement.getAttribute('data-order-items'));

const root = ReactDOM.createRoot(rootElement);
root.render(
    <React.StrictMode>
        <Payment orderId={orderId} orderItems={orderItems} />
    </React.StrictMode>
);
