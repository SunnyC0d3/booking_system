import * as React from 'react';
import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { DownloadHandler } from '@/components/digital/DownloadHandler';

interface DownloadPageProps {
    params: {
        token: string;
    };
}

export async function generateMetadata({ params }: DownloadPageProps): Promise<Metadata> {
    return {
        title: 'Download | Digital Product',
        description: 'Download your digital product files securely.',
        robots: 'noindex, nofollow', // Don't index download pages
    };
}

export default function DownloadPage({ params }: DownloadPageProps) {
    if (!params.token) {
        notFound();
    }

    return <DownloadHandler token={params.token} />;
}