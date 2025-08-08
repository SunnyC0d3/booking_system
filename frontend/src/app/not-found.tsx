'use client'

import * as React from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
    Home,
    ArrowLeft,
    Search,
    Mail,
    Phone,
    Palette,
    ShoppingBag,
    Heart,
    HelpCircle,
    ExternalLink,
} from 'lucide-react';
import { Button, Card, CardContent, Input } from '@/components/ui';
import { MainLayout } from '@/components/layout';

const popularPages = [
    {
        title: 'Custom Labels',
        description: 'Professional labels for any occasion',
        href: '/products/labels',
        icon: Palette,
    },
    {
        title: 'Wedding Invitations',
        description: 'Beautiful wedding invitations',
        href: '/products/invitations',
        icon: Heart,
    },
    {
        title: 'All Products',
        description: 'Browse our complete catalog',
        href: '/products',
        icon: ShoppingBag,
    },
    {
        title: 'Contact Us',
        description: 'Get in touch with our team',
        href: '/contact',
        icon: Mail,
    },
];

// Help links
const helpLinks = [
    { label: 'About Us', href: '/about' },
    { label: 'Services', href: '/services' },
    { label: 'Help Center', href: '/help' },
    { label: 'Terms of Service', href: '/terms' },
    { label: 'Privacy Policy', href: '/privacy' },
];

export default function NotFoundPage() {
    const [searchQuery, setSearchQuery] = React.useState('');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            // Redirect to search page with query
            window.location.href = `/search?q=${encodeURIComponent(searchQuery.trim())}`;
        }
    };

    return (
        <MainLayout showBreadcrumbs={false}>
            <div className="min-h-screen bg-gradient-to-br from-background via-background to-muted/20">
                <section className="min-h-screen flex items-center py-20">
                    <div className="container mx-auto px-4">
                        <div className="max-w-4xl mx-auto text-center">
                            {/* 404 Animation */}
                            <motion.div
                                initial={{ opacity: 0, scale: 0.8 }}
                                animate={{ opacity: 1, scale: 1 }}
                                transition={{ duration: 0.6 }}
                                className="mb-8"
                            >
                                <div className="relative">
                                    <h1 className="text-8xl lg:text-9xl font-bold text-primary/20 select-none">
                                        404
                                    </h1>
                                    <motion.div
                                        initial={{ rotate: 0 }}
                                        animate={{ rotate: [0, 10, -10, 0] }}
                                        transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                                        className="absolute inset-0 flex items-center justify-center"
                                    >
                                        <Palette className="h-16 w-16 lg:h-20 lg:w-20 text-primary" />
                                    </motion.div>
                                </div>
                            </motion.div>

                            {/* Error Message */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                                className="mb-8"
                            >
                                <h2 className="text-3xl lg:text-4xl font-bold text-foreground mb-4">
                                    Oops! Page Not Found
                                </h2>
                                <p className="text-xl text-muted-foreground mb-8 leading-relaxed max-w-2xl mx-auto">
                                    It looks like the creative page you're looking for has wandered off.
                                    Don't worry, let's help you find what you need!
                                </p>
                            </motion.div>

                            {/* Search Bar */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.4 }}
                                className="mb-12"
                            >
                                <Card className="max-w-md mx-auto border-border/50 bg-card/50 backdrop-blur-sm">
                                    <CardContent className="p-4">
                                        <form onSubmit={handleSearch} className="flex gap-2">
                                            <Input
                                                type="text"
                                                placeholder="Search for products or services..."
                                                value={searchQuery}
                                                onChange={(e) => setSearchQuery(e.target.value)}
                                                className="flex-1 bg-background/50"
                                            />
                                            <Button type="submit" size="sm" className="shrink-0">
                                                <Search className="h-4 w-4" />
                                            </Button>
                                        </form>
                                    </CardContent>
                                </Card>
                            </motion.div>

                            {/* Popular Pages */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.6 }}
                                className="mb-12"
                            >
                                <h3 className="text-2xl font-bold text-foreground mb-8">
                                    Popular Destinations
                                </h3>
                                <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    {popularPages.map((page, index) => (
                                        <motion.div
                                            key={index}
                                            initial={{ opacity: 0, y: 20 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ duration: 0.5, delay: 0.1 * index }}
                                            whileHover={{ scale: 1.05 }}
                                            whileTap={{ scale: 0.95 }}
                                        >
                                            <Link href={page.href}>
                                                <Card className="h-full hover:shadow-lg transition-all duration-300 bg-card/50 backdrop-blur-sm border-border/50 cursor-pointer group">
                                                    <CardContent className="p-6 text-center">
                                                        <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-4 group-hover:bg-primary/20 transition-colors">
                                                            <page.icon className="h-6 w-6 text-primary" />
                                                        </div>
                                                        <h4 className="font-semibold text-foreground mb-2 group-hover:text-primary transition-colors">
                                                            {page.title}
                                                        </h4>
                                                        <p className="text-sm text-muted-foreground mb-4">
                                                            {page.description}
                                                        </p>
                                                        <div className="flex items-center justify-center text-primary text-sm group-hover:text-primary/80">
                                                            Visit Page
                                                            <ExternalLink className="ml-1 h-3 w-3" />
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            </Link>
                                        </motion.div>
                                    ))}
                                </div>
                            </motion.div>

                            {/* Action Buttons */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.8 }}
                                className="mb-12"
                            >
                                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                    <Link href="/">
                                        <Button size="lg" leftIcon={<Home className="h-4 w-4" />}>
                                            Go Home
                                        </Button>
                                    </Link>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        onClick={() => window.history.back()}
                                        leftIcon={<ArrowLeft className="h-4 w-4" />}
                                    >
                                        Go Back
                                    </Button>
                                    <Link href="/contact">
                                        <Button
                                            variant="outline"
                                            size="lg"
                                            leftIcon={<Mail className="h-4 w-4" />}
                                        >
                                            Contact Support
                                        </Button>
                                    </Link>
                                </div>
                            </motion.div>

                            {/* Help Links */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 1.0 }}
                                className="border-t border-border/30 pt-8"
                            >
                                <div className="flex items-center justify-center gap-2 mb-4">
                                    <HelpCircle className="h-5 w-5 text-primary" />
                                    <h4 className="text-lg font-semibold text-foreground">
                                        Need Help? Try These Links:
                                    </h4>
                                </div>
                                <div className="flex flex-wrap justify-center gap-4">
                                    {helpLinks.map((link, index) => (
                                        <Link key={index} href={link.href}>
                                            <Button
                                                variant="link"
                                                className="text-primary hover:text-primary/80 font-medium"
                                            >
                                                {link.label}
                                            </Button>
                                        </Link>
                                    ))}
                                </div>
                            </motion.div>

                            {/* Contact Information */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 1.2 }}
                                className="mt-12"
                            >
                                <Card className="bg-muted/30 backdrop-blur-sm border-border/50">
                                    <CardContent className="p-6">
                                        <h4 className="text-lg font-semibold text-foreground mb-4">
                                            Still Can't Find What You're Looking For?
                                        </h4>
                                        <p className="text-muted-foreground mb-4">
                                            Our creative team is here to help! Get in touch and we'll point you in the right direction.
                                        </p>
                                        <div className="flex flex-col sm:flex-row gap-4 justify-center text-sm">
                                            <div className="flex items-center gap-2 justify-center text-muted-foreground">
                                                <Mail className="h-4 w-4 text-primary" />
                                                <a
                                                    href="mailto:hello@creativebusiness.com"
                                                    className="hover:text-primary transition-colors"
                                                >
                                                    hello@creativebusiness.com
                                                </a>
                                            </div>
                                            <div className="flex items-center gap-2 justify-center text-muted-foreground">
                                                <Phone className="h-4 w-4 text-primary" />
                                                <a
                                                    href="tel:+442071234567"
                                                    className="hover:text-primary transition-colors"
                                                >
                                                    +44 20 7123 4567
                                                </a>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        </div>
                    </div>
                </section>
            </div>
        </MainLayout>
    );
}