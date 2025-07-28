import * as React from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowLeft, Palette, Heart } from 'lucide-react';
import { cn } from '@/lib/cn';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    subtitle?: string;
    showBackButton?: boolean;
    backHref?: string;
    className?: string;
}

export const AuthLayout: React.FC<AuthLayoutProps> = ({
                                                          children,
                                                          title,
                                                          subtitle,
                                                          showBackButton = false,
                                                          backHref = '/',
                                                          className,
                                                      }) => {
    return (
        <div className="min-h-screen bg-gradient-creative flex flex-col">
            {/* Header */}
            <header className="w-full p-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    {showBackButton && (
                        <Link
                            href={backHref}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </Link>
                    )}
                </div>

                {/* Logo */}
                <Link
                    href="/"
                    className="flex items-center gap-3 group"
                >
                    <div className="relative">
                        <div className="w-10 h-10 bg-primary rounded-xl flex items-center justify-center group-hover:shadow-glow transition-all duration-300">
                            <Palette className="h-5 w-5 text-primary-foreground" />
                        </div>
                        <div className="absolute -top-1 -right-1 w-4 h-4 bg-cream-400 rounded-full flex items-center justify-center">
                            <Heart className="h-2 w-2 text-white" />
                        </div>
                    </div>
                    <div className="hidden sm:block">
                        <h1 className="font-bold text-xl text-foreground group-hover:text-primary transition-colors">
                            Creative Business
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Labels • Invitations • Stickers
                        </p>
                    </div>
                </Link>

                <div className="w-16" /> {/* Spacer for balance */}
            </header>

            {/* Main Content */}
            <main className="flex-1 flex items-center justify-center p-6">
                <div className="w-full max-w-6xl mx-auto">
                    <div className="grid lg:grid-cols-2 gap-12 items-center">
                        {/* Left Side - Branding & Info */}
                        <motion.div
                            initial={{ opacity: 0, x: -20 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6 }}
                            className="hidden lg:flex flex-col justify-center space-y-8"
                        >
                            <div className="space-y-6">
                                <div className="space-y-4">
                                    <motion.h1
                                        initial={{ opacity: 0, y: 20 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        transition={{ duration: 0.6, delay: 0.1 }}
                                        className="text-4xl xl:text-5xl font-bold text-foreground leading-tight"
                                    >
                                        {title}
                                    </motion.h1>
                                    {subtitle && (
                                        <motion.p
                                            initial={{ opacity: 0, y: 20 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ duration: 0.6, delay: 0.2 }}
                                            className="text-xl text-muted-foreground leading-relaxed"
                                        >
                                            {subtitle}
                                        </motion.p>
                                    )}
                                </div>

                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6, delay: 0.3 }}
                                    className="space-y-4"
                                >
                                    <h3 className="text-lg font-semibold text-foreground">
                                        Perfect for your creative projects:
                                    </h3>
                                    <div className="grid grid-cols-2 gap-4">
                                        {[
                                            { icon: '🏷️', text: 'Custom Labels' },
                                            { icon: '💌', text: 'Invitations' },
                                            { icon: '🎁', text: 'Gift Tags' },
                                            { icon: '✨', text: 'Stickers' },
                                            { icon: '📦', text: 'Packaging' },
                                            { icon: '🌸', text: 'Flower Stands' },
                                        ].map((item, index) => (
                                            <motion.div
                                                key={index}
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

                            {/* Testimonial or Feature */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.8 }}
                                className="p-6 rounded-xl bg-white/70 backdrop-blur-sm border border-white/30"
                            >
                                <div className="flex items-center gap-4 mb-3">
                                    <div className="w-12 h-12 rounded-full bg-primary/20 flex items-center justify-center">
                                        <Heart className="h-6 w-6 text-primary" />
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-foreground">Sarah M.</h4>
                                        <p className="text-sm text-muted-foreground">Happy Customer</p>
                                    </div>
                                </div>
                                <p className="text-sm text-muted-foreground italic">
                                    "Creative Business made my wedding invitations absolutely perfect.
                                    The quality and attention to detail exceeded my expectations!"
                                </p>
                            </motion.div>
                        </motion.div>

                        {/* Right Side - Auth Form */}
                        <motion.div
                            initial={{ opacity: 0, x: 20 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6, delay: 0.2 }}
                            className={cn("flex justify-center", className)}
                        >
                            <div className="w-full max-w-md">
                                {children}
                            </div>
                        </motion.div>
                    </div>
                </div>
            </main>

            {/* Footer */}
            <footer className="w-full p-6 text-center">
                <div className="max-w-6xl mx-auto">
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div className="flex items-center gap-6 text-sm text-muted-foreground">
                            <Link href="/privacy" className="hover:text-foreground transition-colors">
                                Privacy Policy
                            </Link>
                            <Link href="/terms" className="hover:text-foreground transition-colors">
                                Terms of Service
                            </Link>
                            <Link href="/contact" className="hover:text-foreground transition-colors">
                                Contact Us
                            </Link>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            © 2024 Creative Business. Made with{' '}
                            <Heart className="inline h-4 w-4 text-primary" /> for creators.
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    );
};

export default AuthLayout;