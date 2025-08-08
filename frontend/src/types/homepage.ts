export interface Category {
    title: string;
    description: string;
    icon: string;
    href: string;
    color: string;
    stats: {
        products: number;
    };
}

export interface Testimonial {
    id: string;
    name: string;
    role: string;
    content: string;
    rating: number;
    avatar: string;
    verified: boolean;
}

export interface CompanyStats {
    happyCustomers: number;
    projectsCompleted: number;
    satisfactionRate: number;
    reviewsCount: number;
    averageRating: number;
}