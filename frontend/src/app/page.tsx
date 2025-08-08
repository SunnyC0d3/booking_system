import * as React from 'react';
import type {Metadata} from 'next';
import {MainLayout} from '@/components/layout';
import {Category, Testimonial, CompanyStats} from '@/types/homepage';

// Import data fetching functions separately
import {getFeaturedCategories, getTestimonials, getCompanyStats} from '@/lib/data/homepage';

// Import client wrapper component
import {HomepageContent} from '@/components/homepage/HomepageContent';

// Metadata for SEO
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

// Server Component - No Framer Motion here
export default async function HomePage() {
    try {
        // Parallel data fetching with explicit typing and error handling
        const [categories, testimonials, stats]: [Category[], Testimonial[], CompanyStats] = await Promise.all([
            getFeaturedCategories(),
            getTestimonials(),
            getCompanyStats()
        ]);

        return (
            <MainLayout showBreadcrumbs={false}>
                {/* Pass server data to client component */}
                <HomepageContent
                    categories={categories}
                    testimonials={testimonials}
                    stats={stats}
                />
            </MainLayout>
        );
    } catch (error) {
        console.error('Error loading homepage data:', error);

        // Fallback content if data fetching fails
        return (
            <MainLayout showBreadcrumbs={false}>
                <div className="container mx-auto px-4 py-20 text-center">
                    <h1 className="text-4xl font-bold mb-4">Welcome to Creative Business</h1>
                    <p className="text-xl text-muted-foreground mb-8">
                        Professional printing services for all your creative needs.
                    </p>
                    <div className="text-sm text-red-600">
                        Unable to load content. Please refresh the page.
                    </div>
                </div>
            </MainLayout>
        );
    }
}