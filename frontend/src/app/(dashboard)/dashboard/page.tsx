import { Metadata } from 'next';
import {MainLayout} from '@/components/layout/MainLayout';
import DashboardContent from '@/components/dashboard/DashboardContent';
import {RouteGuard} from "@/components/auth/RouteGuard";

export const metadata: Metadata = {
    title: 'Dashboard | Creative Business',
    description: 'Manage your orders, track progress, and explore new products.',
};

export default function DashboardPage() {
    return (
        <RouteGuard requireAuth>
            <MainLayout showBreadcrumbs={false}>
                <DashboardContent />
            </MainLayout>
        </RouteGuard>
    );
}