'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { LucideIcon } from 'lucide-react';

interface Feature {
    icon: LucideIcon;
    title: string;
    description: string;
}

interface FeatureCardProps {
    feature: Feature;
    index: number;
}

export function FeatureCard({ feature, index }: FeatureCardProps) {
    const Icon = feature.icon;

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: index * 0.1 }}
            className="text-center space-y-4"
        >
            <div className="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto">
                <Icon className="h-8 w-8 text-primary" />
            </div>
            <div className="space-y-2">
                <h3 className="text-xl font-semibold text-foreground">
                    {feature.title}
                </h3>
                <p className="text-muted-foreground">
                    {feature.description}
                </p>
            </div>
        </motion.div>
    );
}
