'use client';

import { motion } from 'framer-motion';
import { Heart } from 'lucide-react';
import { useMemo } from 'react';

interface AuthBrandingProps {
    title: string;
    subtitle?: string;
}

// Static data moved outside component to prevent recreation
const FEATURES = [
    { icon: '🏷️', text: 'Custom Labels' },
    { icon: '💌', text: 'Invitations' },
    { icon: '🎁', text: 'Gift Tags' },
    { icon: '✨', text: 'Stickers' },
    { icon: '📦', text: 'Packaging' },
    { icon: '🌸', text: 'Flower Stands' },
] as const;

const TESTIMONIAL = {
    name: 'Sarah M.',
    role: 'Happy Customer',
    quote: "Creative Business made my wedding invitations absolutely perfect. The quality and attention to detail exceeded my expectations!"
} as const;

export function AuthBranding({ title, subtitle }: AuthBrandingProps) {
    // Memoize animation variants to prevent recreation
    const containerVariants = useMemo(() => ({
        initial: { opacity: 0, x: -20 },
        animate: { opacity: 1, x: 0 },
        transition: { duration: 0.6 }
    }), []);

    const titleVariants = useMemo(() => ({
        initial: { opacity: 0, y: 20 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: 0.6, delay: 0.1 }
    }), []);

    const subtitleVariants = useMemo(() => ({
        initial: { opacity: 0, y: 20 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: 0.6, delay: 0.2 }
    }), []);

    const featuresVariants = useMemo(() => ({
        initial: { opacity: 0, y: 20 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: 0.6, delay: 0.3 }
    }), []);

    const testimonialVariants = useMemo(() => ({
        initial: { opacity: 0, y: 20 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: 0.6, delay: 0.8 }
    }), []);

    return (
        <motion.div
            {...containerVariants}
            className="hidden lg:flex flex-col justify-center space-y-8"
        >
            <div className="space-y-6">
                <div className="space-y-4">
                    <motion.h1
                        {...titleVariants}
                        className="text-4xl xl:text-5xl font-bold text-foreground leading-tight"
                    >
                        {title}
                    </motion.h1>
                    {subtitle && (
                        <motion.p
                            {...subtitleVariants}
                            className="text-xl text-muted-foreground leading-relaxed"
                        >
                            {subtitle}
                        </motion.p>
                    )}
                </div>

                <motion.div
                    {...featuresVariants}
                    className="space-y-4"
                >
                    <h3 className="text-lg font-semibold text-foreground">
                        Perfect for your creative projects:
                    </h3>
                    <div className="grid grid-cols-2 gap-4">
                        {FEATURES.map((item, index) => (
                            <motion.div
                                key={item.text}
                                initial={{ opacity: 0, scale: 0.8 }}
                                animate={{ opacity: 1, scale: 1 }}
                                transition={{ duration: 0.4, delay: 0.4 + index * 0.1 }}
                                className="flex items-center gap-3 p-3 rounded-lg bg-white/50 backdrop-blur-sm border border-white/20"
                            >
                                <span className="text-2xl">{item.icon}</span>
                                <span className="text-sm font-medium text-foreground">
                  {item.text}
                </span>
                            </motion.div>
                        ))}
                    </div>
                </motion.div>
            </div>

            {/* Testimonial */}
            <motion.div
                {...testimonialVariants}
                className="p-6 rounded-xl bg-white/70 backdrop-blur-sm border border-white/30"
            >
                <div className="flex items-center gap-4 mb-3">
                    <div className="w-12 h-12 rounded-full bg-primary/20 flex items-center justify-center">
                        <Heart className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h4 className="font-semibold text-foreground">{TESTIMONIAL.name}</h4>
                        <p className="text-sm text-muted-foreground">{TESTIMONIAL.role}</p>
                    </div>
                </div>
                <p className="text-sm text-muted-foreground italic">
                    "{TESTIMONIAL.quote}"
                </p>
            </motion.div>
        </motion.div>
    );
}
