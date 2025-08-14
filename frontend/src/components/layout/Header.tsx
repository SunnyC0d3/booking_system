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
        isEmailVerified,
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
        <header className="sticky top-0 z-50 bg-white border-b border-gray-200 shadow-sm">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center h-16">
                    {/* Logo */}
                    <div className="flex items-center">
                        <Link href="/" className="flex items-center space-x-2">
                            <div className="h-8 w-8 bg-blue-600 rounded-lg flex items-center justify-center">
                                <span className="text-white font-bold text-sm">CB</span>
                            </div>
                            <span className="hidden sm:block text-xl font-semibold text-gray-900">
                Creative Business
              </span>
                        </Link>
                    </div>

                    {/* Navigation */}
                    <nav className="hidden md:flex items-center space-x-8">
                        <Link
                            href="/products"
                            className="text-gray-600 hover:text-gray-900 transition-colors"
                        >
                            Products
                        </Link>
                        <Link
                            href="/about"
                            className="text-gray-600 hover:text-gray-900 transition-colors"
                        >
                            About
                        </Link>
                        <Link
                            href="/contact"
                            className="text-gray-600 hover:text-gray-900 transition-colors"
                        >
                            Contact
                        </Link>
                    </nav>

                    {/* Right Side */}
                    <div className="flex items-center space-x-4">
                        <Link href="/cart">
                            <Button variant="ghost" size="sm" className="relative">
                                <ShoppingCart className="h-5 w-5" />
                            </Button>
                        </Link>

                        {isAuthenticated ? (
                            <>
                                {needsEmailVerification && (
                                    <Badge variant="secondary" className="hidden sm:inline-flex">
                                        Verify Email
                                    </Badge>
                                )}

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            className="relative h-8 w-8 rounded-full"
                                        >
                                            <Avatar className="h-8 w-8">
                                                <AvatarImage
                                                    src={user?.avatar_url}
                                                    alt={user?.name || 'User'}
                                                />
                                                <AvatarFallback>
                                                    {getUserInitials(user?.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent className="w-56" align="end" forceMount>
                                        <DropdownMenuLabel className="font-normal">
                                            <div className="flex flex-col space-y-1">
                                                <p className="text-sm font-medium leading-none">
                                                    {user?.name || 'User'}
                                                </p>
                                                <p className="text-xs leading-none text-muted-foreground">
                                                    {user?.email}
                                                </p>
                                                {user?.role?.name && (
                                                    <Badge variant="outline" className="w-fit text-xs">
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
                                                    <Link href="/admin" className="cursor-pointer">
                                                        <Shield className="mr-2 h-4 w-4" />
                                                        Admin Panel
                                                    </Link>
                                                </DropdownMenuItem>
                                            </>
                                        )}

                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            className="cursor-pointer text-red-600 focus:text-red-600"
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
                                {/* Guest Navigation */}
                                <Link href="/login">
                                    <Button variant="ghost" size="sm">
                                        Sign In
                                    </Button>
                                </Link>
                                <Link href="/register">
                                    <Button size="sm">
                                        Sign Up
                                    </Button>
                                </Link>
                            </>
                        )}

                        <Button
                            variant="ghost"
                            size="sm"
                            className="md:hidden"
                            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                        >
                            <Menu className="h-5 w-5" />
                        </Button>
                    </div>
                </div>

                {isMobileMenuOpen && (
                    <div className="md:hidden">
                        <div className="px-2 pt-2 pb-3 space-y-1 border-t border-gray-200">
                            <Link
                                href="/products"
                                className="block px-3 py-2 text-base font-medium text-gray-600 hover:text-gray-900"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                Products
                            </Link>
                            <Link
                                href="/about"
                                className="block px-3 py-2 text-base font-medium text-gray-600 hover:text-gray-900"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                About
                            </Link>
                            <Link
                                href="/contact"
                                className="block px-3 py-2 text-base font-medium text-gray-600 hover:text-gray-900"
                                onClick={() => setIsMobileMenuOpen(false)}
                            >
                                Contact
                            </Link>

                            {isAuthenticated && (
                                <>
                                    <div className="border-t border-gray-200 pt-2 mt-2">
                                        <Link
                                            href="/dashboard"
                                            className="block px-3 py-2 text-base font-medium text-gray-600 hover:text-gray-900"
                                            onClick={() => setIsMobileMenuOpen(false)}
                                        >
                                            Dashboard
                                        </Link>
                                        <Link
                                            href="/profile"
                                            className="block px-3 py-2 text-base font-medium text-gray-600 hover:text-gray-900"
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