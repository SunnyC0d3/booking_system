'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { ArrowRight, Star, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui';

interface HeroSectionProps {
    stats: {
        averageRating: number;
        reviewsCount: number;
    };
}

export function HeroSection({ stats }: HeroSectionProps) {
    return (
        <section className="relative overflow-hidden bg-gradient-creative">
            <div className="container mx-auto px-4 py-20 lg:py-32">
                <div className="grid lg:grid-cols-2 gap-12 items-center">
                    <motion.div
                        initial={{ opacity: 0, x: -20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.6 }}
                        className="space-y-8"
                    >
                        <div className="space-y-4">
                            <motion.h1
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.1 }}
                                className="text-4xl lg:text-6xl font-bold text-foreground leading-tight"
                            >
                                Transform Your{' '}
                                <span className="text-gradient">Creative Vision</span>
                            </motion.h1>
                            <motion.p
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                                className="text-xl text-muted-foreground leading-relaxed"
                            >
                                Professional labels, invitations, stickers, and custom printing
                                services for every occasion. Quality craftsmanship meets creative excellence.
                            </motion.p>
                        </div>

                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.3 }}
                            className="flex flex-col sm:flex-row gap-4"
                        >
                            <Button
                                href="/products"
                                size="lg"
                                className="w-full sm:w-auto"
                                rightIcon={<ArrowRight className="h-4 w-4" />}
                            >
                                Browse Products
                            </Button>
                            <Button
                                href="/services/custom-design"
                                variant="outline"
                                size="lg"
                                className="w-full sm:w-auto"
                            >
                                Custom Design
                            </Button>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.4 }}
                            className="flex items-center gap-6 pt-4"
                        >
                            <div className="flex items-center gap-1">
                                {[...Array(5)].map((_, i) => (
                                    <Star key={i} className="h-5 w-5 fill-primary text-primary" />
                                ))}
                            </div>
                            <div className="text-sm text-muted-foreground">
                <span className="font-semibold text-foreground">
                  {stats.averageRating}/5
                </span>{' '}
                                from {stats.reviewsCount}+ reviews
                            </div>
                        </motion.div>
                    </motion.div>

                    <motion.div
                        initial={{ opacity: 0, x: 20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.6, delay: 0.2 }}
                        className="relative"
                    >
                        <div className="aspect-square rounded-2xl bg-white/20 backdrop-blur-sm border border-white/30 p-8 flex items-center justify-center">
                            <div className="text-center space-y-4">
                                <div className="w-32 h-32 bg-primary/20 rounded-full flex items-center justify-center mx-auto">
                                    <Sparkles className="h-16 w-16 text-primary" />
                                </div>
                                <p className="text-lg font-medium text-foreground">
                                    Your creativity, our expertise
                                </p>
                            </div>
                        </div>
                    </motion.div>
                </div>
            </div>
        </section>
    );
}