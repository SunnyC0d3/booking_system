import * as React from 'react';
import { CategoryCard } from './CategoryCard';

interface Category {
    title: string;
    description: string;
    icon: string;
    href: string;
    color: string;
    stats: {
        products: number;
    };
}

interface ProductCategoriesSectionProps {
    categories: Category[];
}

export function ProductCategoriesSection({ categories }: ProductCategoriesSectionProps) {
    return (
        <section className="py-20 bg-background">
            <div className="container mx-auto px-4">
                <div className="text-center space-y-4 mb-16">
                    <h2 className="text-3xl lg:text-4xl font-bold text-foreground">
                        Our Product Categories
                    </h2>
                    <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                        Discover our wide range of professional printing services designed to bring your creative
                        projects to life
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {categories.map((category, index) => (
                        <CategoryCard
                            key={category.href}
                            category={category}
                            index={index}
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}