'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { Button, Card, CardContent } from '@/components/ui';

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

interface CategoryCardProps {
    category: Category;
    index: number;
}

export function CategoryCard({ category, index }: CategoryCardProps) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: index * 0.1 }}
        >
            <Button
                href={category.href}
                variant="ghost"
                className="h-auto w-full p-0 group"
            >
                <Card className="h-full card-hover cursor-pointer w-full">
                    <CardContent className="p-6 text-center space-y-4">
                        <div
                            className={`w-16 h-16 rounded-2xl bg-gradient-to-br ${category.color} mx-auto flex items-center justify-center text-2xl`}
                        >
                            {category.icon}
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-xl font-semibold text-foreground group-hover:text-primary transition-colors">
                                {category.title}
                            </h3>
                            <p className="text-muted-foreground text-sm">
                                {category.description}
                            </p>
                            <p className="text-xs text-muted-foreground font-medium">
                                {category.stats.products} products available
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </Button>
        </motion.div>
    );
}