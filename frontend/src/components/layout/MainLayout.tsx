'use client'

import * as React from 'react';
import {Header} from '@/components/layout/Header';
import {Footer} from '@/components/layout/Footer';
import Link from 'next/link';
import { cn } from '@/lib/cn';
import { Menu, X, Home, ShoppingBag, Heart, User } from 'lucide-react';

interface MainLayoutProps {
    children: React.ReactNode;
    showBreadcrumbs?: boolean;
    className?: string;
}

export function MainLayout({
                               children,
                               showBreadcrumbs = true,
                               className
                           }: MainLayoutProps) {
    const [isMenuOpen, setIsMenuOpen] = React.useState(false);

    const navigationItems = [
        { name: 'Home', href: '/', icon: Home },
        { name: 'Products', href: '/products', icon: ShoppingBag },
        { name: 'Wishlist', href: '/wishlist', icon: Heart },
        { name: 'Account', href: '/account', icon: User },
    ];

    return (
        <div className={cn('min-h-screen bg-background flex flex-col', className)}>
            {/* Header */}
            <Header />

            {/* Breadcrumbs */}
            {showBreadcrumbs && (
                <nav className="border-b border-border/40 bg-muted/30">
                    <div className="container px-4 py-3">
                        <div className="text-sm text-muted-foreground">
                            <Link href="/" className="hover:text-foreground transition-colors">
                                Home
                            </Link>
                            {/* Add breadcrumb logic here based on current path */}
                        </div>
                    </div>
                </nav>
            )}

            {/* Main content */}
            <main className="flex-1">
                {children}
            </main>

            {/* Footer */}
            <Footer />
        </div>
    );
}