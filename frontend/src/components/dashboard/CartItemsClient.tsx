'use client'

import * as React from 'react';
import { useCartItemCount } from '@/stores/cartStore';

interface CartItemsClientProps {
    children: (cartItemCount: number) => React.ReactNode;
}

export default function CartItemsClient({ children }: CartItemsClientProps) {
    const cartItemCount = useCartItemCount();

    return <>{children(cartItemCount)}</>;
}