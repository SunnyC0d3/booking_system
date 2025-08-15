'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
    Badge,
    Button,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui';
import {
    User,
    Settings,
    LogOut,
    ShoppingCart,
    Menu,
    Shield,
    Download
} from 'lucide-react';

export function Header() {
    const {
        isAuthenticated,
        user,
        handleLogout,
        isAdmin,
        needsEmailVerification
    } = useAuthUtils();

    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    const getUserInitials = (name?: string) => {
        if (!name) return 'U';
        return name
            .split(' ')
            .map(word => word.charAt(0).toUpperCase())
            .slice(0, 2)
            .join('');
    };

    const handleLogoutClick = async () => {
        try {
            await handleLogout();
        } catch (error) {
            console.error('Logout failed:', error);
        }
    };

    return (
        <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div className="container flex h-16 items-center justify-between px-4">
                <Link href="/" className="flex items-center space-x-2">
                    <div className="h-8 w-8 rounded-lg bg-primary flex items-center justify-center">
                        <span className="text-sm font-bold text-primary-foreground">CB</span>
                    </div>
                    <span className="hidden font-bold sm:inline-block">
                        Creative Business
                    </span>
                </Link>

                <nav className="hidden md:flex items-center space-x-6 text-sm font-medium">
                    <Link
                        href="/products"
                        className="transition-colors hover:text-foreground/80 text-foreground/60"
                    >
                        Products
                    </Link>
                    <Link
                        href="/categories"
                        className="transition-colors hover:text-foreground/80 text-foreground/60"
                    >
                        Categories
                    </Link>
                    <Link
                        href="/about"
                        className="transition-colors hover:text-foreground/80 text-foreground/60"
                    >
                        About
                    </Link>
                    <Link
                        href="/contact"
                        className="transition-colors hover:text-foreground/80 text-foreground/60"
                    >
                        Contact
                    </Link>
                </nav>

                <div className="flex items-center space-x-4">
                    <Link href="/cart">
                        <Button variant="ghost" size="sm" className="relative">
                            <ShoppingCart className="h-4 w-4" />
                            <span className="sr-only">Shopping cart</span>
                        </Button>
                    </Link>

                    <Button
                        variant="ghost"
                        size="sm"
                        className="md:hidden"
                        onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                    >
                        <Menu className="h-4 w-4" />
                        <span className="sr-only">Menu</span>
                    </Button>

                    {isAuthenticated ? (
                        <>
                            {needsEmailVerification && (
                                <Badge variant="secondary" className="hidden sm:inline-flex bg-yellow-100 text-yellow-800">
                                    Verify Email
                                </Badge>
                            )}

                            <DropdownMenu>
                                <DropdownMenuTrigger className="relative h-8 w-8 rounded-full hover:bg-accent transition-colors">
                                    <Avatar className="h-8 w-8 ring-2 ring-primary/20 ring-offset-2 ring-offset-background">
                                        <AvatarImage
                                            src={user?.avatar_url}
                                            alt={user?.name || 'User'}
                                        />
                                        <AvatarFallback className="bg-primary/10 text-primary font-medium">
                                            {getUserInitials(user?.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-56" align="end">
                                    <DropdownMenuLabel className="font-normal">
                                        <div className="flex flex-col space-y-1">
                                            <p className="text-sm font-medium leading-none text-foreground">
                                                {user?.name || 'User'}
                                            </p>
                                            <p className="text-xs leading-none text-muted-foreground">
                                                {user?.email}
                                            </p>
                                            {user?.role?.name && (
                                                <Badge variant="outline" className="w-fit text-xs border-primary/20 text-primary">
                                                    {user.role.name}
                                                </Badge>
                                            )}
                                        </div>
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />

                                    <DropdownMenuItem onClick={() => window.location.href = '/dashboard'}>
                                        <User className="mr-2 h-4 w-4" />
                                        Dashboard
                                    </DropdownMenuItem>

                                    <DropdownMenuItem onClick={() => window.location.href = '/profile'}>
                                        <Settings className="mr-2 h-4 w-4" />
                                        Profile Settings
                                    </DropdownMenuItem>

                                    <DropdownMenuItem onClick={() => window.location.href = '/orders'}>
                                        <ShoppingCart className="mr-2 h-4 w-4" />
                                        My Orders
                                    </DropdownMenuItem>

                                    <DropdownMenuItem onClick={() => window.location.href = '/account/digital-library'}>
                                        <Download className="mr-2 h-4 w-4" />
                                        Digital Library
                                    </DropdownMenuItem>

                                    {isAdmin && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                onClick={() => window.location.href = '/admin'}
                                                className="text-primary"
                                            >
                                                <Shield className="mr-2 h-4 w-4" />
                                                Admin Panel
                                            </DropdownMenuItem>
                                        </>
                                    )}

                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        className="text-destructive focus:text-destructive"
                                        onClick={handleLogoutClick}
                                    >
                                        <LogOut className="mr-2 h-4 w-4" />
                                        Sign Out
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </>
                    ) : (
                        <>
                            <Link href="/login">
                                <Button variant="ghost" size="sm">
                                    Sign In
                                </Button>
                            </Link>
                            <Link href="/register">
                                <Button size="sm">
                                    Get Started
                                </Button>
                            </Link>
                        </>
                    )}
                </div>
            </div>

            {isMobileMenuOpen && (
                <div className="md:hidden border-t bg-background">
                    <div className="container px-4 py-4">
                        <nav className="flex flex-col space-y-3">
                            <Link
                                href="/products"
                                className="text-sm font-medium transition-colors hover:text-primary"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                Products
                            </Link>
                            <Link
                                href="/categories"
                                className="text-sm font-medium transition-colors hover:text-primary"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                Categories
                            </Link>
                            <Link
                                href="/about"
                                className="text-sm font-medium transition-colors hover:text-primary"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                About
                            </Link>
                            <Link
                                href="/contact"
                                className="text-sm font-medium transition-colors hover:text-primary"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                Contact
                            </Link>

                            {isAuthenticated && (
                                <>
                                    <hr className="my-2" />
                                    <Link
                                        href="/dashboard"
                                        className="text-sm font-medium transition-colors hover:text-primary"
                                        onClick={() => setIsMobileMenuOpen(false)}
                                    >
                                        Dashboard
                                    </Link>
                                    <Link
                                        href="/profile"
                                        className="text-sm font-medium transition-colors hover:text-primary"
                                        onClick={() => setIsMobileMenuOpen(false)}
                                    >
                                        Profile
                                    </Link>
                                    <Link
                                        href="/orders"
                                        className="text-sm font-medium transition-colors hover:text-primary"
                                        onClick={() => setIsMobileMenuOpen(false)}
                                    >
                                        My Orders
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </div>
            )}
        </header>
    );
}