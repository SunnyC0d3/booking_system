import * as React from 'react';
import { Metadata } from 'next';
import { DashboardLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { AddressManagement } from '@/components/dashboard/AddressManagement';

export const metadata: Metadata = {
    title: 'Addresses | Creative Business',
    description: 'Manage your shipping and billing addresses.',
};

function AddressesPage() {
    return (
        <RouteGuard requireAuth>
            <DashboardLayout
                title="Address Book"
                description="Manage your shipping and billing addresses"
                showBreadcrumbs
            >
                <AddressManagement />
            </DashboardLayout>
        </RouteGuard>
    );
}

export default AddressesPage;