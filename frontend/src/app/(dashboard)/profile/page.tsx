import * as React from 'react';
import { Metadata } from 'next';
import { DashboardLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import {
    UserProfileCard,
    AccountSecurityCard,
    PreferencesCard
} from '@/components/dashboard/UserProfile';
import { useAuth } from '@/stores/authStore';

export const metadata: Metadata = {
    title: 'Profile | Creative Business',
    description: 'Manage your profile information and account settings.',
};

function ProfilePage() {
    const { user } = useAuth();

    if (!user) {
        return null; // RouteGuard will handle redirect
    }

    return (
        <RouteGuard requireAuth>
            <DashboardLayout
                title="Profile Settings"
                description="Manage your personal information and account preferences"
                showBreadcrumbs
            >
                <div className="space-y-8">
                    {/* Profile Information */}
                    <UserProfileCard user={user} />

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {/* Account Security */}
                        <AccountSecurityCard />

                        {/* Preferences */}
                        <PreferencesCard />
                    </div>
                </div>
            </DashboardLayout>
        </RouteGuard>
    );
}

export default ProfilePage;