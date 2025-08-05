import * as React from 'react';
import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { DigitalProductInfo } from '@/components/digital/DigitalProductInfo';

interface DigitalInfoPageProps {
    params: {
        id: string;
    };
}

export async function generateMetadata({ params }: DigitalInfoPageProps): Promise<Metadata> {
    // Use the product ID parameter to create more specific metadata
    const productId = params.id;

    // In a real app, you'd fetch the product data here using the productId
    // const product = await fetchProduct(productId);

    return {
        title: `Digital Product Information - Product ${productId}`,
        description: `Technical specifications and download information for digital product ${productId}.`,
    };
}

export default function DigitalInfoPage({ params }: DigitalInfoPageProps) {
    if (!params.id) {
        notFound();
    }

    return <DigitalProductInfo productId={params.id} />;
}