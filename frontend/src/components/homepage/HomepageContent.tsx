'use client';

import * as React from 'react';
import {Suspense} from 'react';
import type {Category, Testimonial, CompanyStats} from '@/types/homepage';

import {HeroSection} from './HeroSection';
import {ProductCategoriesSection} from './ProductCategoriesSection';
import {FeaturesSection} from './FeaturesSection';
import {TestimonialsSection} from './TestimonialsSection';
import {CTASection} from './CTASection';

import {HeroSkeleton, CategoriesSkeleton, FeaturesSkeleton} from './skeletons';

interface HomepageContentProps {
    categories: Category[];
    testimonials: Testimonial[];
    stats: CompanyStats;
}

export function HomepageContent({categories, testimonials, stats}: HomepageContentProps) {
    return (
        <>
            {/* Hero Section */}
            <Suspense fallback={<HeroSkeleton/>}>
                <HeroSection stats={stats}/>
            </Suspense>

            {/* Product Categories */}
            <Suspense fallback={<CategoriesSkeleton/>}>
                <ProductCategoriesSection categories={categories}/>
            </Suspense>

            {/* Features Section */}
            <Suspense fallback={<FeaturesSkeleton/>}>
                <FeaturesSection/>
            </Suspense>

            {/* Testimonials */}
            <Suspense fallback={<div className="py-20 text-center">Loading testimonials...</div>}>
                <TestimonialsSection testimonials={testimonials}/>
            </Suspense>

            {/* CTA Section */}
            <CTASection stats={stats}/>
        </>
    );
}
