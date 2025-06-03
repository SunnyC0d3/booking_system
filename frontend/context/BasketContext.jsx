import React, {createContext, useContext, useState} from 'react';

const BasketContext = createContext();

export const BasketProvider = ({children}) => {
    const [basket, setBasket] = useState([
        {
            id: '5',
            name: 'Product 5',
            price: 371.99,
            quantity: 1,
            thumbnail: ''
        },
        {
            id: '11',
            name: 'Product 11',
            price: 786.99,
            quantity: 1,
            thumbnail: ''
        }
    ]);

    const basketCount = basket.reduce((total, item) => total + item.quantity, 0);

    return (
        <BasketContext.Provider value={{basket, setBasket, basketCount}}>
            {children}
        </BasketContext.Provider>
    );
};

export const useBasket = () => useContext(BasketContext);
