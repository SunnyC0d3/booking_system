import { Metadata } from 'next';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { SecurityDashboard } from '@/components/auth/SecurityDashboard';

export const metadata: Metadata = {
    title: 'Security - Account Settings',
    description: 'Manage your account security settings and monitor activity.',
};

export default function SecurityPage() {
    return (
        <RouteGuard requireAuth>
            <div className="container mx-auto py-8 px-4">
                <SecurityDashboard />
            </div>
        </RouteGuard>
    );
}