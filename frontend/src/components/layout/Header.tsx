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
        return name.split(' ')
            .map(word => word[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const handleLogoutClick = () => {
        handleLogout('/login', 'Logged out successfully');
    };

    return (
        <header className="sticky top-0 z-50 bg-background/80 backdrop-blur-sm border-b border-border shadow-soft">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center h-16">
                    <div className="flex items-center">
                        <Link href="/" className="flex items-center space-x-2 group">
                            <div className="h-8 w-8 bg-gradient-to-br from-primary to-primary-600 rounded-lg flex items-center justify-center transition-transform group-hover:scale-105">
                                <span className="text-primary-foreground font-bold text-sm">CB</span>
                            </div>
                            <span className="hidden sm:block text-xl font-semibold text-foreground group-hover:text-primary transition-colors">
                                Creative Business
                            </span>
                        </Link>
                    </div>

                    <nav className="hidden md:flex items-center space-x-8">
                        <Link
                            href="/products"
                            className="text-muted-foreground hover:text-foreground transition-colors duration-200 font-medium"
                        >
                            Products
                        </Link>
                        <Link
                            href="/about"
                            className="text-muted-foreground hover:text-foreground transition-colors duration-200 font-medium"
                        >
                            About
                        </Link>
                        <Link
                            href="/contact"
                            className="text-muted-foreground hover:text-foreground transition-colors duration-200 font-medium"
                        >
                            Contact
                        </Link>
                    </nav>

                    <div className="flex items-center space-x-3">
                        <Link href="/cart">
                            <Button variant="ghost" size="sm" className="relative hover:bg-accent">
                                <ShoppingCart className="h-5 w-5" />
                            </Button>
                        </Link>

                        {isAuthenticated ? (
                            <>
                                {needsEmailVerification && (
                                    <Badge variant="secondary" className="hidden sm:inline-flex bg-warning text-warning-foreground">
                                        Verify Email
                                    </Badge>
                                )}

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            className="relative h-8 w-8 rounded-full hover:bg-accent"
                                        >
                                            <Avatar className="h-8 w-8 ring-2 ring-primary/20 ring-offset-2 ring-offset-background">
                                                <AvatarImage
                                                    src={user?.avatar_url}
                                                    alt={user?.name || 'User'}
                                                />
                                                <AvatarFallback className="bg-primary/10 text-primary font-medium">
                                                    {getUserInitials(user?.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent className="w-56" align="end" forceMount>
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

                                        <DropdownMenuItem asChild>
                                            <Link href="/dashboard" className="cursor-pointer">
                                                <User className="mr-2 h-4 w-4" />
                                                Dashboard
                                            </Link>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem asChild>
                                            <Link href="/profile" className="cursor-pointer">
                                                <Settings className="mr-2 h-4 w-4" />
                                                Profile Settings
                                            </Link>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem asChild>
                                            <Link href="/orders" className="cursor-pointer">
                                                <ShoppingCart className="mr-2 h-4 w-4" />
                                                My Orders
                                            </Link>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem asChild>
                                            <Link href="/account/digital-library" className="cursor-pointer">
                                                <Download className="mr-2 h-4 w-4" />
                                                Digital Library
                                            </Link>
                                        </DropdownMenuItem>

                                        {isAdmin && (
                                            <>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem asChild>
                                                    <Link href="/admin" className="cursor-pointer text-primary">
                                                        <Shield className="mr-2 h-4 w-4" />
                                                        Admin Panel
                                                    </Link>
                                                </DropdownMenuItem>
                                            </>
                                        )}

                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            className="cursor-pointer text-destructive focus:text-destructive"
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
                                    <Button variant="ghost" size="sm" className="hover:bg-accent">
                                        Sign In
                                    </Button>
                                </Link>
                                <Link href="/register">
                                    <Button variant="ghost" size="sm" className="hover:bg-accent">
                                        Sign Up
                                    </Button>
                                </Link>
                            </>
                        )}

                        <Button
                            variant="ghost"
                            size="sm"
                            className="md:hidden hover:bg-accent"
                            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                        >
                            <Menu className="h-5 w-5" />
                        </Button>
                    </div>
                </div>

                {isMobileMenuOpen && (
                    <div className="md:hidden animate-in slide-in-from-top-2 duration-200">
                        <div className="px-2 pt-2 pb-3 space-y-1 border-t border-border bg-card/50 backdrop-blur-sm">
                            <Link
                                href="/products"
                                className="block px-3 py-2 text-base font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                Products
                            </Link>
                            <Link
                                href="/about"
                                className="block px-3 py-2 text-base font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                About
                            </Link>
                            <Link
                                href="/contact"
                                className="block px-3 py-2 text-base font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                Contact
                            </Link>

                            {isAuthenticated && (
                                <>
                                    <div className="border-t border-border pt-2 mt-2">
                                        <Link
                                            href="/dashboard"
                                            className="block px-3 py-2 text-base font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
                                            onClick={() => setIsMobileMenuOpen(false)}
                                        >
                                            Dashboard
                                        </Link>
                                        <Link
                                            href="/profile"
                                            className="block px-3 py-2 text-base font-medium text-muted-foreground hover:text-foreground hover:bg-accent rounded-md transition-colors"
                                            onClick={() => setIsMobileMenuOpen(false)}
                                        >
                                            Profile
                                        </Link>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </header>
    );
}