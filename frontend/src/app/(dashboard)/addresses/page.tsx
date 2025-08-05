'use client'

import * as React from 'react';
import { DashboardLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { AddressManagement } from '@/components/dashboard/AddressManagement';

function AddressesPage() {
    return (
        <RouteGuard requireAuth>
            <DashboardLayout
                title="Address Book"
                description="Manage your shipping and billing addresses"
            >
                <AddressManagement />
            </DashboardLayout>
        </RouteGuard>
    );
}

export default AddressesPage;