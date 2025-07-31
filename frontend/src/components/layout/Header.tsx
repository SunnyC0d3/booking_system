'use client'

import * as React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Search,
    ShoppingCart,
    User,
    Menu,
    X,
    Palette,
    Heart,
    Package,
    Settings,
    LogOut,
    Bell,
    Star,
} from 'lucide-react';
import { Button, Input } from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { cn } from '@/lib/cn';

interface HeaderProps {
    className?: string;
}

// Navigation items
const navigationItems = [
    {
        label: 'Products',
        href: '/products',
        children: [
            { label: 'Labels', href: '/products/labels', description: 'Custom labels for any occasion' },
            { label: 'Invitations', href: '/products/invitations', description: 'Beautiful wedding and party invitations' },
            { label: 'Gift Tags', href: '/products/gift-tags', description: 'Perfect finishing touches for gifts' },
            { label: 'Stickers', href: '/products/stickers', description: 'Custom stickers and decals' },
            { label: 'Greeting Cards', href: '/products/greeting-cards', description: 'Personalized greeting cards' },
            { label: 'Packaging', href: '/products/packaging', description: 'Professional packaging inserts' },
        ],
    },
    {
        label: 'Collections',
        href: '/collections',
        children: [
            { label: 'Wedding Collection', href: '/collections/wedding' },
            { label: 'Birthday Collection', href: '/collections/birthday' },
            { label: 'Business Collection', href: '/collections/business' },
            { label: 'Holiday Collection', href: '/collections/holiday' },
        ],
    },
    {
        label: 'Services',
        href: '/services',
        children: [
            { label: 'Custom Design', href: '/services/custom-design' },
            { label: 'Flower Stands', href: '/services/flower-stands' },
            { label: 'Bulk Orders', href: '/services/bulk-orders' },
            { label: 'Rush Printing', href: '/services/rush-printing' },
        ],
    },
    { label: 'About', href: '/about' },
    { label: 'Contact', href: '/contact' },
];

export const Header: React.FC<HeaderProps> = ({ className }) => {
    const router = useRouter();
    const { user, isAuthenticated, logout } = useAuth();
    const [isMenuOpen, setIsMenuOpen] = React.useState(false);
    const [activeDropdown, setActiveDropdown] = React.useState<string | null>(null);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [isUserMenuOpen, setIsUserMenuOpen] = React.useState(false);

    // Close dropdowns when clicking outside
    React.useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Element;
            if (!target.closest('.dropdown-container')) {
                setActiveDropdown(null);
                setIsUserMenuOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.push(`/search?q=${encodeURIComponent(searchQuery.trim())}`);
            setSearchQuery('');
        }
    };

    const handleLogout = async () => {
        try {
            await logout();
            router.push('/');
        } catch (error) {
            console.error('Logout failed:', error);
        }
    };

    return (
        <header className={cn('sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 shadow-soft', className)}>
            <div className="container mx-auto px-4">
                <div className="flex h-16 items-center justify-between">
                    {/* Logo */}
                    <Link href="/" className="flex items-center gap-3 group">
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
                            <p className="text-xs text-muted-foreground -mt-1">
                                Professional Printing
                            </p>
                        </div>
                    </Link>

                    {/* Desktop Navigation */}
                    <nav className="hidden lg:flex items-center space-x-8">
                        {navigationItems.map((item) => (
                            <div
                                key={item.href}
                                className="relative dropdown-container"
                                onMouseEnter={() => item.children && setActiveDropdown(item.label)}
                                onMouseLeave={() => setActiveDropdown(null)}
                            >
                                <Link
                                    href={item.href}
                                    className={cn(
                                        'text-sm font-medium transition-colors hover:text-primary',
                                        'flex items-center gap-1 py-2'
                                    )}
                                >
                                    {item.label}
                                    {item.children && (
                                        <motion.div
                                            animate={{ rotate: activeDropdown === item.label ? 180 : 0 }}
                                            transition={{ duration: 0.2 }}
                                        >
                                            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                            </svg>
                                        </motion.div>
                                    )}
                                </Link>

                                {/* Dropdown Menu */}
                                {item.children && (
                                    <AnimatePresence>
                                        {activeDropdown === item.label && (
                                            <motion.div
                                                initial={{ opacity: 0, y: 10 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                exit={{ opacity: 0, y: 10 }}
                                                transition={{ duration: 0.2 }}
                                                className="absolute top-full left-0 mt-2 w-80 bg-background border rounded-xl shadow-soft-lg p-4"
                                            >
                                                <div className="space-y-2">
                                                    {item.children.map((child) => (
                                                        <Link
                                                            key={child.href}
                                                            href={child.href}
                                                            className="block p-3 rounded-lg hover:bg-muted transition-colors group"
                                                        >
                                                            <div className="font-medium text-sm text-foreground group-hover:text-primary">
                                                                {child.label}
                                                            </div>
                                                            {child.description && (
                                                                <div className="text-xs text-muted-foreground mt-1">
                                                                    {child.description}
                                                                </div>
                                                            )}
                                                        </Link>
                                                    ))}
                                                </div>
                                            </motion.div>
                                        )}
                                    </AnimatePresence>
                                )}
                            </div>
                        ))}
                    </nav>

                    {/* Search Bar */}
                    <div className="hidden md:flex flex-1 max-w-md mx-8">
                        <form onSubmit={handleSearch} className="relative w-full">
                            <Input
                                type="search"
                                placeholder="Search products..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                leftIcon={<Search className="h-4 w-4" />}
                                className="pr-4"
                            />
                        </form>
                    </div>

                    {/* Right Side Actions */}
                    <div className="flex items-center gap-4">
                        {/* Mobile Search */}
                        <Button
                            variant="ghost"
                            size="icon"
                            className="md:hidden"
                            onClick={() => {/* TODO: Open search modal */}}
                        >
                            <Search className="h-5 w-5" />
                        </Button>

                        {/* Cart */}
                        <Link href="/cart">
                            <Button variant="ghost" size="icon" className="relative">
                                <ShoppingCart className="h-5 w-5" />
                                {/* Cart count badge - TODO: Connect to cart store */}
                                <span className="absolute -top-1 -right-1 w-5 h-5 bg-primary text-primary-foreground text-xs rounded-full flex items-center justify-center">
                  2
                </span>
                            </Button>
                        </Link>

                        {/* User Menu */}
                        {isAuthenticated ? (
                            <div className="relative dropdown-container">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                                    className="relative"
                                >
                                    {user?.avatar ? (
                                        <img
                                            src={user.avatar}
                                            alt={user.name}
                                            className="w-8 h-8 rounded-full object-cover"
                                        />
                                    ) : (
                                        <div className="w-8 h-8 rounded-full bg-primary flex items-center justify-center">
                      <span className="text-primary-foreground text-sm font-medium">
                        {user?.name?.charAt(0).toUpperCase()}
                      </span>
                                        </div>
                                    )}
                                    {/* Notification dot */}
                                    <div className="absolute top-0 right-0 w-3 h-3 bg-success rounded-full border-2 border-background" />
                                </Button>

                                {/* User Dropdown */}
                                <AnimatePresence>
                                    {isUserMenuOpen && (
                                        <motion.div
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: 10 }}
                                            transition={{ duration: 0.2 }}
                                            className="absolute right-0 top-full mt-2 w-64 bg-background border rounded-xl shadow-soft-lg p-2"
                                        >
                                            {/* User Info */}
                                            <div className="px-3 py-2 border-b">
                                                <div className="font-medium text-sm text-foreground">
                                                    {user?.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {user?.email}
                                                </div>
                                            </div>

                                            {/* Menu Items */}
                                            <div className="py-2 space-y-1">
                                                <Link
                                                    href="/dashboard"
                                                    className="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-muted transition-colors"
                                                >
                                                    <User className="h-4 w-4" />
                                                    Dashboard
                                                </Link>
                                                <Link
                                                    href="/orders"
                                                    className="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-muted transition-colors"
                                                >
                                                    <Package className="h-4 w-4" />
                                                    My Orders
                                                </Link>
                                                <Link
                                                    href="/wishlist"
                                                    className="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-muted transition-colors"
                                                >
                                                    <Heart className="h-4 w-4" />
                                                    Wishlist
                                                </Link>
                                                <Link
                                                    href="/settings"
                                                    className="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-muted transition-colors"
                                                >
                                                    <Settings className="h-4 w-4" />
                                                    Settings
                                                </Link>
                                            </div>

                                            {/* Logout */}
                                            <div className="border-t pt-2">
                                                <button
                                                    onClick={handleLogout}
                                                    className="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-muted transition-colors w-full text-left text-destructive hover:text-destructive"
                                                >
                                                    <LogOut className="h-4 w-4" />
                                                    Sign Out
                                                </button>
                                            </div>
                                        </motion.div>
                                    )}
                                </AnimatePresence>
                            </div>
                        ) : (
                            <div className="hidden sm:flex items-center gap-2">
                                <Link href="/login">
                                    <Button variant="ghost" size="sm">
                                        Sign In
                                    </Button>
                                </Link>
                                <Link href="/register">
                                    <Button variant="default" size="sm">
                                        Sign Up
                                    </Button>
                                </Link>
                            </div>
                        )}

                        {/* Mobile Menu Toggle */}
                        <Button
                            variant="ghost"
                            size="icon"
                            className="lg:hidden"
                            onClick={() => setIsMenuOpen(!isMenuOpen)}
                        >
                            {isMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </Button>
                    </div>
                </div>

                {/* Mobile Navigation */}
                <AnimatePresence>
                    {isMenuOpen && (
                        <motion.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            transition={{ duration: 0.3 }}
                            className="lg:hidden border-t mt-4 pt-4 pb-4"
                        >
                            {/* Mobile Search */}
                            <div className="mb-4">
                                <form onSubmit={handleSearch}>
                                    <Input
                                        type="search"
                                        placeholder="Search products..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        leftIcon={<Search className="h-4 w-4" />}
                                    />
                                </form>
                            </div>

                            {/* Mobile Navigation Items */}
                            <div className="space-y-4">
                                {navigationItems.map((item) => (
                                    <div key={item.href}>
                                        <Link
                                            href={item.href}
                                            className="block py-2 text-base font-medium text-foreground hover:text-primary transition-colors"
                                            onClick={() => setIsMenuOpen(false)}
                                        >
                                            {item.label}
                                        </Link>
                                        {item.children && (
                                            <div className="ml-4 mt-2 space-y-2">
                                                {item.children.map((child) => (
                                                    <Link
                                                        key={child.href}
                                                        href={child.href}
                                                        className="block py-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                                                        onClick={() => setIsMenuOpen(false)}
                                                    >
                                                        {child.label}
                                                    </Link>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {/* Mobile Auth Buttons */}
                            {!isAuthenticated && (
                                <div className="flex flex-col gap-2 mt-6 pt-6 border-t">
                                    <Link href="/login" onClick={() => setIsMenuOpen(false)}>
                                        <Button variant="outline" className="w-full">
                                            Sign In
                                        </Button>
                                    </Link>
                                    <Link href="/register" onClick={() => setIsMenuOpen(false)}>
                                        <Button variant="default" className="w-full">
                                            Sign Up
                                        </Button>
                                    </Link>
                                </div>
                            )}
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </header>
    );
};

export default Header;