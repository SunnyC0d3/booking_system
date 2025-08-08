import {Category, Testimonial, CompanyStats} from '@/types/homepage';

// Move the async functions here
async function getFeaturedCategories(): Promise<Category[]> {
    // Simulate API call - replace with actual API endpoint
    await new Promise(resolve => setTimeout(resolve, 100)); // Remove in production

    return [
        {
            title: 'Custom Labels',
            description: 'Professional labels for products, events, and personal use',
            icon: '🏷️',
            href: '/products/labels',
            color: 'from-pink-500 to-rose-500',
            stats: { products: 150 }
        },
        {
            title: 'Wedding Invitations',
            description: 'Elegant invitations for your special day',
            icon: '💌',
            href: '/products/invitations',
            color: 'from-purple-500 to-pink-500',
            stats: { products: 89 }
        },
        {
            title: 'Gift Tags',
            description: 'Perfect finishing touches for any gift',
            icon: '🎁',
            href: '/products/gift-tags',
            color: 'from-emerald-500 to-teal-500',
            stats: { products: 75 }
        },
        {
            title: 'Stickers & Decals',
            description: 'Custom stickers for branding and decoration',
            icon: '✨',
            href: '/products/stickers',
            color: 'from-blue-500 to-cyan-500',
            stats: { products: 120 }
        },
        {
            title: 'Greeting Cards',
            description: 'Personalized cards for every occasion',
            icon: '💝',
            href: '/products/greeting-cards',
            color: 'from-orange-500 to-red-500',
            stats: { products: 95 }
        },
        {
            title: 'Packaging',
            description: 'Professional packaging inserts and materials',
            icon: '📦',
            href: '/products/packaging',
            color: 'from-indigo-500 to-purple-500',
            stats: { products: 60 }
        },
    ];
}

async function getTestimonials(): Promise<Testimonial[]> {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 150));

    return [
        {
            id: '1',
            name: 'Sarah Johnson',
            role: 'Wedding Planner',
            content: 'The wedding invitations were absolutely perfect! The quality exceeded our expectations and the attention to detail was incredible.',
            rating: 5,
            avatar: '/testimonials/sarah.jpg',
            verified: true
        },
        {
            id: '2',
            name: 'Michael Chen',
            role: 'Small Business Owner',
            content: 'Creative Business helped us create amazing product labels that really make our brand stand out. Highly recommended!',
            rating: 5,
            avatar: '/testimonials/michael.jpg',
            verified: true
        },
        {
            id: '3',
            name: 'Emily Rodriguez',
            role: 'Event Coordinator',
            content: 'From gift tags to custom stickers, everything was perfect for our corporate event. Professional service and beautiful results.',
            rating: 5,
            avatar: '/testimonials/emily.jpg',
            verified: true
        },
    ];
}

async function getCompanyStats(): Promise<CompanyStats> {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 200));

    return {
        happyCustomers: 500,
        projectsCompleted: 10000,
        satisfactionRate: 99,
        reviewsCount: 523,
        averageRating: 4.9
    };
}

export { getFeaturedCategories, getTestimonials, getCompanyStats };