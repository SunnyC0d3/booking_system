import * as React from 'react';
import { Shield, Truck, Palette, Heart } from 'lucide-react';
import { FeatureCard } from './FeatureCard';

const features = [
    {
        icon: Shield,
        title: 'Quality Guaranteed',
        description: 'Premium materials and professional printing with satisfaction guarantee',
    },
    {
        icon: Truck,
        title: 'Fast Shipping',
        description: 'Quick turnaround times with reliable delivery options',
    },
    {
        icon: Palette,
        title: 'Custom Design',
        description: 'Personalized designs tailored to your vision and brand',
    },
    {
        icon: Heart,
        title: 'Made with Love',
        description: 'Crafted with attention to detail and passion for creativity',
    },
];

export function FeaturesSection() {
    return (
        <section className="py-20 bg-muted/30">
            <div className="container mx-auto px-4">
                <div className="text-center space-y-4 mb-16">
                    <h2 className="text-3xl lg:text-4xl font-bold text-foreground">
                        Why Choose Creative Business?
                    </h2>
                    <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                        We're committed to delivering exceptional quality and service for all your creative printing needs
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    {features.map((feature, index) => (
                        <FeatureCard key={feature.title} feature={feature} index={index} />
                    ))}
                </div>
            </div>
        </section>
    );
}
