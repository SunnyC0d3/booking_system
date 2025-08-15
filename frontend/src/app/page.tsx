import * as React from 'react';
import type {Metadata} from 'next';
import {Category, Testimonial, CompanyStats} from '@/types/homepage';
import {getFeaturedCategories, getTestimonials, getCompanyStats} from '@/lib/data/homepage';
import {HomepageContent} from '@/components/homepage/HomepageContent';
import {MainLayout} from '@/components/layout/MainLayout';

export const metadata: Metadata = {
    title: 'Creative Business | Professional Labels, Invitations & Custom Printing',
    description: 'Transform your creative vision with professional custom labels, wedding invitations, gift tags, stickers, greeting cards, and packaging. Quality craftsmanship with fast shipping.',
    keywords: ['custom labels', 'wedding invitations', 'gift tags', 'stickers', 'greeting cards', 'professional printing'],
    openGraph: {
        title: 'Creative Business | Professional Printing Services',
        description: 'Quality custom printing for labels, invitations, stickers, and more. Professional results with personal service.',
        images: ['/og-home.jpg'],
        type: 'website',
    },
    twitter: {
        card: 'summary_large_image',
        title: 'Creative Business | Professional Printing Services',
        description: 'Quality custom printing for labels, invitations, stickers, and more.',
        images: ['/og-home.jpg'],
    },
};

export default async function HomePage() {
    const categories: Category[] = await getFeaturedCategories();
    const testimonials: Testimonial[] = await getTestimonials();
    const stats: CompanyStats = await getCompanyStats();

    return (
        <MainLayout showBreadcrumbs={false}>
            <HomepageContent
                categories={categories}
                testimonials={testimonials}
                stats={stats}
            />
        </MainLayout>
    );
}
