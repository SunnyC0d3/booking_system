import React from 'react';
import ReactDOM from 'react-dom/client';
import Payment from './components/Payment';

const rootElement = document.getElementById('app');
const orderId = rootElement.getAttribute('data-order-id');

const root = ReactDOM.createRoot(rootElement);
root.render(
    <React.StrictMode>
        <Payment orderId={orderId} />
    </React.StrictMode>
);
