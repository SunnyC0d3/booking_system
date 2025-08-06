'use client';

import * as React from 'react';
import Link from 'next/link';
import {
    Download,
    Key,
    AlertCircle,
    Clock,
    Package,
    RefreshCw
} from 'lucide-react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Button,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui';
import { DigitalProductCard } from './DigitalProductCard';
import { LicenseManager } from './LicenseManager';
import { useDigitalProducts } from '@/hooks/useDigitalProducts';
import { cn } from '@/lib/cn';

interface DigitalLibraryProps {
    className?: string;
    showStats?: boolean;
}

export const DigitalLibrary: React.FC<DigitalLibraryProps> = ({
                                                                  className,
                                                                  showStats = true
                                                              }) => {
    const {
        downloadAccesses,
        licenseKeys,
        statistics,
        loading,
        error,
        refreshLibrary
    } = useDigitalProducts();

    const [activeTab, setActiveTab] = React.useState('downloads');

    if (loading) {
        return (
            <div className="space-y-6">
                {Array.from({ length: 3 }).map((_, i) => (
                    <Card key={i} className="animate-pulse">
                        <CardContent className="p-6">
                            <div className="space-y-4">
                                <div className="h-6 bg-muted rounded w-1/3" />
                                <div className="h-4 bg-muted rounded w-full" />
                                <div className="h-4 bg-muted rounded w-2/3" />
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        );
    }

    if (error) {
        return (
            <Card className="border-destructive/20 bg-destructive/5">
                <CardContent className="p-6 text-center">
                    <AlertCircle className="h-12 w-12 text-destructive mx-auto mb-4" />
                    <h3 className="text-lg font-semibold text-destructive mb-2">
                        Error Loading Digital Library
                    </h3>
                    <p className="text-muted-foreground mb-4">{error}</p>
                    <Button onClick={refreshLibrary} variant="outline">
                        <RefreshCw className="h-4 w-4 mr-2" />
                        Try Again
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className={cn('space-y-6', className)}>
            {/* Statistics Overview */}
            {showStats && statistics && (
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Package className="h-8 w-8 text-blue-600" />
                                <div>
                                    <p className="text-2xl font-bold">{statistics.total_products}</p>
                                    <p className="text-sm text-muted-foreground">Digital Products</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Download className="h-8 w-8 text-green-600" />
                                <div>
                                    <p className="text-2xl font-bold">{statistics.total_downloads}</p>
                                    <p className="text-sm text-muted-foreground">Downloads</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Key className="h-8 w-8 text-purple-600" />
                                <div>
                                    <p className="text-2xl font-bold">{statistics.active_licenses}</p>
                                    <p className="text-sm text-muted-foreground">Active Licenses</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Clock className="h-8 w-8 text-orange-600" />
                                <div>
                                    <p className="text-2xl font-bold">{statistics.expiring_soon}</p>
                                    <p className="text-sm text-muted-foreground">Expiring Soon</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Main Content Tabs */}
            <Tabs value={activeTab} onValueChange={setActiveTab}>
                <TabsList className="grid w-full grid-cols-3">
                    <TabsTrigger value="downloads">Downloads</TabsTrigger>
                    <TabsTrigger value="licenses">Licenses</TabsTrigger>
                    <TabsTrigger value="statistics">Statistics</TabsTrigger>
                </TabsList>

                <TabsContent value="downloads" className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold">Your Downloads</h3>
                        <Button onClick={refreshLibrary} variant="outline" size="sm">
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Refresh
                        </Button>
                    </div>

                    {downloadAccesses.length === 0 ? (
                        <Card>
                            <CardContent className="p-8 text-center">
                                <Download className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No Downloads Available</h3>
                                <p className="text-muted-foreground mb-4">
                                    You haven't purchased any digital products yet.
                                </p>
                                <Button>
                                    <Link href="/products?type=digital">
                                        Browse Digital Products
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-4">
                            {downloadAccesses.map((access) => (
                                <DigitalProductCard key={access.id} downloadAccess={access} />
                            ))}
                        </div>
                    )}
                </TabsContent>

                <TabsContent value="licenses" className="space-y-4">
                    <LicenseManager licenseKeys={licenseKeys} />
                </TabsContent>

                <TabsContent value="statistics" className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Download Activity</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex justify-between">
                                        <span>This Month</span>
                                        <span className="font-semibold">{statistics?.downloads_this_month || 0}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Total Downloads</span>
                                        <span className="font-semibold">{statistics?.total_downloads || 0}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Average per Product</span>
                                        <span className="font-semibold">
                                            {statistics?.total_products ?
                                                Math.round((statistics.total_downloads || 0) / statistics.total_products * 10) / 10
                                                : 0
                                            }
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>License Status</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex justify-between">
                                        <span>Active Licenses</span>
                                        <span className="font-semibold text-green-600">
                                            {statistics?.active_licenses || 0}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Expiring Soon</span>
                                        <span className="font-semibold text-orange-600">
                                            {statistics?.expiring_soon || 0}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Expired</span>
                                        <span className="font-semibold text-red-600">
                                            {statistics?.expired_licenses || 0}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    );
};