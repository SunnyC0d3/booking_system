import * as React from 'react';
import Link from 'next/link';
import { Package, Clock, MapPin, User, ArrowRight } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui';
import { cn } from '@/lib/cn';

// Types
interface QuickAction {
    title: string;
    description: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    color: string;
}

const quickActions: QuickAction[] = [
    {
        title: 'Browse New Products',
        description: 'Discover our latest designs and collections',
        href: '/products',
        icon: Package,
        color: 'bg-primary',
    },
    {
        title: 'Track Orders',
        description: 'Check the status of your current orders',
        href: '/orders',
        icon: Clock,
        color: 'bg-blue-500',
    },
    {
        title: 'Manage Addresses',
        description: 'Update your shipping and billing addresses',
        href: '/addresses',
        icon: MapPin,
        color: 'bg-green-500',
    },
    {
        title: 'Account Settings',
        description: 'Update your profile and preferences',
        href: '/profile',
        icon: User,
        color: 'bg-purple-500',
    },
];

export default function QuickActions() {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {quickActions.map((action, index) => (
                    <Link key={index} href={action.href}>
                        <div className="flex items-center gap-3 p-3 rounded-lg hover:bg-muted/50 transition-colors cursor-pointer group">
                            <div className={cn('p-2 rounded-lg text-white transition-transform group-hover:scale-105', action.color)}>
                                <action.icon className="h-4 w-4" />
                            </div>
                            <div className="flex-1">
                                <p className="font-medium text-sm">
                                    {action.title}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {action.description}
                                </p>
                            </div>
                            <ArrowRight className="h-4 w-4 text-muted-foreground transition-transform group-hover:translate-x-1" />
                        </div>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}