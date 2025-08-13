import { Metadata } from 'next';
import {MainLayout} from '@/components/layout/MainLayout';
import DashboardContent from '@/components/dashboard/DashboardContent';

export const metadata: Metadata = {
    title: 'Dashboard | Creative Business',
    description: 'Manage your orders, track progress, and explore new products.',
};

export default function DashboardPage() {
    return (
        <MainLayout showBreadcrumbs={false}>
            <DashboardContent />
        </MainLayout>
    );
}