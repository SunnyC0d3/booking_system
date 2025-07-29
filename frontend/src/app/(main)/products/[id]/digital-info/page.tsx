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
    // In a real app, you'd fetch the product data here
    return {
        title: 'Digital Product Information',
        description: 'Technical specifications and download information for this digital product.',
    };
}

export default function DigitalInfoPage({ params }: DigitalInfoPageProps) {
    if (!params.id) {
        notFound();
    }

    return <DigitalProductInfo productId={params.id} />;
}