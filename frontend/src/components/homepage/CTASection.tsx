'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui';

interface CTASectionProps {
    stats: {
        happyCustomers: number;
        projectsCompleted: number;
        satisfactionRate: number;
    };
}

export function CTASection({ stats }: CTASectionProps) {
    return (
        <section className="py-20 bg-gradient-to-r from-primary to-primary/80">
            <div className="container mx-auto px-4 text-center">
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6 }}
                    className="space-y-8"
                >
                    <div className="space-y-4">
                        <h2 className="text-3xl lg:text-4xl font-bold text-primary-foreground">
                            Ready to Bring Your Vision to Life?
                        </h2>
                        <p className="text-xl text-primary-foreground/90 max-w-2xl mx-auto">
                            Start your creative project today with our professional printing services.
                            Quality, creativity, and exceptional service guaranteed.
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row gap-4 justify-center">
                        <Button
                            href="/products"
                            variant="secondary"
                            size="lg"
                            className="w-full sm:w-auto"
                            rightIcon={<ArrowRight className="h-4 w-4" />}
                        >
                            Start Shopping
                        </Button>
                        <Button
                            href="/services/custom-design"
                            variant="outline"
                            size="lg"
                            className="w-full sm:w-auto bg-transparent border-primary-foreground text-primary-foreground hover:bg-primary-foreground hover:text-primary"
                        >
                            Get Custom Quote
                        </Button>
                    </div>

                    <div className="flex items-center justify-center gap-8 pt-8 text-primary-foreground/80">
                        <div className="text-center">
                            <div className="text-2xl font-bold">{stats.happyCustomers}+</div>
                            <div className="text-sm">Happy Customers</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold">{stats.projectsCompleted.toLocaleString()}+</div>
                            <div className="text-sm">Projects Completed</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold">{stats.satisfactionRate}%</div>
                            <div className="text-sm">Satisfaction Rate</div>
                        </div>
                    </div>
                </motion.div>
            </div>
        </section>
    );
}