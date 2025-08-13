import * as React from 'react';
import {Metadata} from 'next';
import {cn} from '@/lib/cn';

interface DashboardLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
    className?: string;
    headerActions?: React.ReactNode;
}

export default function DashboardLayout({
                                            children,
                                            title,
                                            description,
                                            className,
                                            headerActions,
                                        }: DashboardLayoutProps) {
    return (
        <div className={cn('min-h-screen bg-background', className)}>
            {(title || description || headerActions) && (
                <div className="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="container mx-auto px-4 py-6">
                        <div className="flex items-center justify-between">
                            <div className="space-y-1">
                                {title && (
                                    <h1 className="text-2xl font-semibold tracking-tight">
                                        {title}
                                    </h1>
                                )}
                                {description && (
                                    <p className="text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                )}
                            </div>
                            {headerActions && (
                                <div className="flex items-center space-x-2">
                                    {headerActions}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <main className="container mx-auto px-4 py-6">
                {children}
            </main>
        </div>
    );
}

export const generateDashboardMetadata = (
    title: string,
    description?: string
): Metadata => {
    return {
        title: `${title} | Dashboard`,
        description: description || `${title} page in your dashboard`,
    };
};