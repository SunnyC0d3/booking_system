'use client'

import * as React from 'react';
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
            <header className="sticky top-0 z-50 w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="container flex h-16 items-center justify-between px-4">
                    {/* Logo */}
                    <Link href="/" className="flex items-center space-x-2">
                        <div className="h-8 w-8 rounded-lg bg-primary flex items-center justify-center">
                            <span className="text-primary-foreground font-bold text-lg">CB</span>
                        </div>
                        <span className="font-bold text-xl hidden sm:inline-block">
              Creative Business
            </span>
                    </Link>

                    {/* Desktop Navigation */}
                    <nav className="hidden md:flex items-center space-x-6">
                        {navigationItems.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className="flex items-center space-x-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                            >
                                <item.icon className="h-4 w-4" />
                                <span>{item.name}</span>
                            </Link>
                        ))}
                    </nav>

                    {/* Mobile Menu Button */}
                    <button
                        className="md:hidden p-2 rounded-md hover:bg-accent"
                        onClick={() => setIsMenuOpen(!isMenuOpen)}
                        aria-label="Toggle menu"
                    >
                        {isMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
                    </button>
                </div>

                {/* Mobile Navigation */}
                {isMenuOpen && (
                    <div className="md:hidden">
                        <nav className="border-t border-border bg-background px-4 py-4 space-y-3">
                            {navigationItems.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className="flex items-center space-x-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground py-2"
                                    onClick={() => setIsMenuOpen(false)}
                                >
                                    <item.icon className="h-4 w-4" />
                                    <span>{item.name}</span>
                                </Link>
                            ))}
                        </nav>
                    </div>
                )}
            </header>

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
            <footer className="border-t border-border bg-background mt-auto">
                <div className="container px-4 py-8">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div className="col-span-1 md:col-span-2">
                            <div className="flex items-center space-x-2 mb-4">
                                <div className="h-8 w-8 rounded-lg bg-primary flex items-center justify-center">
                                    <span className="text-primary-foreground font-bold text-lg">CB</span>
                                </div>
                                <span className="font-bold text-xl">Creative Business</span>
                            </div>
                            <p className="text-muted-foreground max-w-md">
                                Professional custom labels, invitations, stickers, and creative printing services for every occasion.
                            </p>
                        </div>

                        <div>
                            <h3 className="font-semibold mb-3">Products</h3>
                            <ul className="space-y-2 text-sm text-muted-foreground">
                                <li><Link href="/products/labels" className="hover:text-foreground">Labels</Link></li>
                                <li><Link href="/products/invitations" className="hover:text-foreground">Invitations</Link></li>
                                <li><Link href="/products/stickers" className="hover:text-foreground">Stickers</Link></li>
                            </ul>
                        </div>

                        <div>
                            <h3 className="font-semibold mb-3">Support</h3>
                            <ul className="space-y-2 text-sm text-muted-foreground">
                                <li><Link href="/contact" className="hover:text-foreground">Contact</Link></li>
                                <li><Link href="/help" className="hover:text-foreground">Help Center</Link></li>
                                <li><Link href="/privacy" className="hover:text-foreground">Privacy Policy</Link></li>
                            </ul>
                        </div>
                    </div>

                    <div className="border-t border-border mt-8 pt-8 text-center text-sm text-muted-foreground">
                        © 2025 Creative Business. All rights reserved.
                    </div>
                </div>
            </footer>
        </div>
    );
}