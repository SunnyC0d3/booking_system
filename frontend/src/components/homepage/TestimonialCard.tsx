'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { Star, CheckCircle } from 'lucide-react';
import { Card, CardContent } from '@/components/ui';

interface Testimonial {
    id: string;
    name: string;
    role: string;
    content: string;
    rating: number;
    avatar?: string;
    verified?: boolean;
}

interface TestimonialCardProps {
    testimonial: Testimonial;
    index: number;
}

export function TestimonialCard({ testimonial, index }: TestimonialCardProps) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: index * 0.1 }}
        >
            <Card className="h-full">
                <CardContent className="p-6 space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1">
                            {[...Array(testimonial.rating)].map((_, i) => (
                                <Star key={i} className="h-4 w-4 fill-primary text-primary" />
                            ))}
                        </div>
                        {testimonial.verified && (
                            <CheckCircle className="h-4 w-4 text-green-600" />
                        )}
                    </div>
                    <p className="text-muted-foreground italic">
                        "{testimonial.content}"
                    </p>
                    <div className="flex items-center gap-3">
                        {testimonial.avatar && (
                            <img
                                src={testimonial.avatar}
                                alt={testimonial.name}
                                className="w-10 h-10 rounded-full object-cover"
                            />
                        )}
                        <div>
                            <div className="font-semibold text-foreground">
                                {testimonial.name}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {testimonial.role}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </motion.div>
    );
}