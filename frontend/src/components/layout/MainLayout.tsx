import * as React from 'react';
import { motion } from 'framer-motion';
import Header from './Header';
import Footer from './Footer';
import { BreadcrumbContainer } from './Breadcrumbs';
import { cn } from '@/lib/cn';

interface MainLayoutProps {
    children: React.ReactNode;
    className?: string;
    showBreadcrumbs?: boolean;
    showFooter?: boolean;
    breadcrumbItems?: Array<{
        label: string;
        href?: string;
        current?: boolean;
    }>;
    pageTitle?: string;
    pageDescription?: string;
}

export const MainLayout: React.FC<MainLayoutProps> = ({
                                                          children,
                                                          className,
                                                          showBreadcrumbs = true,
                                                          showFooter = true,
                                                          breadcrumbItems,
                                                          pageTitle,
                                                          pageDescription,
                                                      }) => {
    return (
        <div className="min-h-screen flex flex-col bg-background">
            {/* Header */}
            <Header />

            {/* Breadcrumbs */}
            {showBreadcrumbs && (
                <BreadcrumbContainer>
                    {breadcrumbItems && (
                        <nav className="flex items-center space-x-1 text-sm">
                            <ol className="flex items-center space-x-1">
                                {breadcrumbItems.map((item, index) => (
                                    <React.Fragment key={item.href || item.label}>
                                        <li>
                                            {item.href && !item.current ? (
                                                <a
                                                    href={item.href}
                                                    className="text-muted-foreground hover:text-foreground transition-colors font-medium"
                                                >
                                                    {item.label}
                                                </a>
                                            ) : (
                                                <span
                                                    className={cn(
                                                        'font-medium',
                                                        item.current
                                                            ? 'text-foreground'
                                                            : 'text-muted-foreground'
                                                    )}
                                                    aria-current={item.current ? 'page' : undefined}
                                                >
                          {item.label}
                        </span>
                                            )}
                                        </li>
                                        {index < breadcrumbItems.length - 1 && (
                                            <li className="flex items-center text-muted-foreground">
                                                <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
                                                </svg>
                                            </li>
                                        )}
                                    </React.Fragment>
                                ))}
                            </ol>
                        </nav>
                    )}
                </BreadcrumbContainer>
            )}

            {/* Page Header */}
            {(pageTitle || pageDescription) && (
                <div className="border-b bg-muted/30">
                    <div className="container mx-auto px-4 py-8">
                        <div className="max-w-4xl">
                            {pageTitle && (
                                <motion.h1
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.5 }}
                                    className="text-3xl lg:text-4xl font-bold text-foreground mb-4"
                                >
                                    {pageTitle}
                                </motion.h1>
                            )}
                            {pageDescription && (
                                <motion.p
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.5, delay: 0.1 }}
                                    className="text-lg text-muted-foreground leading-relaxed"
                                >
                                    {pageDescription}
                                </motion.p>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Main Content */}
            <main className={cn('flex-1', className)}>
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.5, delay: 0.2 }}
                >
                    {children}
                </motion.div>
            </main>

            {/* Footer */}
            {showFooter && <Footer />}
        </div>
    );
};

// Specialized layout components for different sections

export const DashboardLayout: React.FC<{
    children: React.ReactNode;
    title?: string;
    description?: string;
}> = ({ children, title, description }) => (
    <MainLayout
        showBreadcrumbs={true}
        pageTitle={title}
        pageDescription={description}
        className="container mx-auto px-4 py-8"
    >
        {children}
    </MainLayout>
);

export const ProductLayout: React.FC<{
    children: React.ReactNode;
    title?: string;
    description?: string;
}> = ({ children, title, description }) => (
    <MainLayout
        showBreadcrumbs={true}
        pageTitle={title}
        pageDescription={description}
    >
        {children}
    </MainLayout>
);

export const ContentLayout: React.FC<{
    children: React.ReactNode;
    title?: string;
    description?: string;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full';
}> = ({ children, title, description, maxWidth = 'lg' }) => (
    <MainLayout
        showBreadcrumbs={true}
        pageTitle={title}
        pageDescription={description}
    >
        <div className="container mx-auto px-4 py-12">
            <div className={cn(
                'mx-auto prose prose-gray dark:prose-invert',
                {
                    'max-w-sm': maxWidth === 'sm',
                    'max-w-md': maxWidth === 'md',
                    'max-w-lg': maxWidth === 'lg',
                    'max-w-xl': maxWidth === 'xl',
                    'max-w-2xl': maxWidth === '2xl',
                    'max-w-none': maxWidth === 'full',
                }
            )}>
                {children}
            </div>
        </div>
    </MainLayout>
);

export const CheckoutLayout: React.FC<{
    children: React.ReactNode;
    currentStep?: number;
    totalSteps?: number;
}> = ({ children, currentStep, totalSteps }) => (
    <MainLayout
        showBreadcrumbs={false}
        showFooter={false}
        className="bg-muted/30"
    >
        <div className="container mx-auto px-4 py-8">
            {/* Checkout Progress */}
            {currentStep && totalSteps && (
                <div className="mb-8">
                    <div className="flex items-center justify-center space-x-4">
                        {Array.from({ length: totalSteps }, (_, i) => (
                            <div
                                key={i}
                                className={cn(
                                    'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
                                    i + 1 <= currentStep
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground'
                                )}
                            >
                                {i + 1}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="max-w-4xl mx-auto">
                {children}
            </div>
        </div>
    </MainLayout>
);

export default MainLayout;