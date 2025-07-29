import * as React from 'react';
import { Metadata } from 'next';
import { DashboardLayout } from '@/components/layout';
import { DigitalLibrary } from '@/components/digital';
import { RouteGuard } from '@/components/auth/RouteGuard';

export const metadata: Metadata = {
    title: 'Digital Library | Your Account',
    description: 'Manage your digital downloads, license keys, and digital product library.',
};

export default function DigitalLibraryPage() {
    return (
        <RouteGuard requireAuth>
            <DashboardLayout
                title="Digital Library"
                description="Access your purchased digital products, downloads, and license keys"
            >
                <DigitalLibrary showStats={true} />
            </DashboardLayout>
        </RouteGuard>
    );
}