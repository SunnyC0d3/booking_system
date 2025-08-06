'use client'

import * as React from 'react';
import { MainLayout } from '@/components/layout';
import {
    Monitor,
    Cpu,
    Download,
    Shield,
    Package,
    CheckCircle,
    AlertTriangle
} from 'lucide-react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Badge,
} from '@/components/ui';

interface DigitalProductInfoProps {
    productId: string;
}

interface ProductDigitalInfo {
    product_type: string;
    requires_license: boolean;
    supported_platforms: string[];
    system_requirements: string;
    latest_version: string;
    download_info: {
        download_limit: number;
        download_expiry_days: number;
        auto_delivery: boolean;
    };
}

export const DigitalProductInfo: React.FC<DigitalProductInfoProps> = ({ productId }) => {
    const [productInfo, setProductInfo] = React.useState<ProductDigitalInfo | null>(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    React.useEffect(() => {
        fetchProductDigitalInfo();
    }, [productId]);

    const fetchProductDigitalInfo = async () => {
        try {
            setLoading(true);
            const response = await fetch(`/api/v1/products/${productId}/digital-info`);

            if (!response.ok) {
                throw new Error('Product not found or not digital');
            }

            const data = await response.json();
            setProductInfo(data.data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load product information');
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-8">
                    <div className="space-y-6">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Card key={i} className="animate-pulse">
                                <CardHeader>
                                    <div className="h-6 bg-muted rounded w-1/3" />
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        <div className="h-4 bg-muted rounded w-full" />
                                        <div className="h-4 bg-muted rounded w-2/3" />
                                        <div className="h-4 bg-muted rounded w-1/2" />
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </MainLayout>
        );
    }

    if (error) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-8">
                    <Card className="border-destructive/20 bg-destructive/5">
                        <CardContent className="p-6 text-center">
                            <AlertTriangle className="h-12 w-12 text-destructive mx-auto mb-4" />
                            <h3 className="text-lg font-semibold text-destructive mb-2">
                                Error Loading Product Information
                            </h3>
                            <p className="text-muted-foreground">{error}</p>
                        </CardContent>
                    </Card>
                </div>
            </MainLayout>
        );
    }

    if (!productInfo) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-8">
                    <Card>
                        <CardContent className="p-6 text-center">
                            <Package className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                            <h3 className="text-lg font-semibold mb-2">
                                No Digital Information Available
                            </h3>
                            <p className="text-muted-foreground">
                                This product doesn't have digital information available.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </MainLayout>
        );
    }

    return (
        <MainLayout>
            <div className="container mx-auto px-4 py-8">
                <div className="space-y-6">
                    {/* Product Type & License */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5" />
                                Product Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 className="font-semibold mb-2">Product Type</h4>
                                    <Badge variant="secondary" className="mb-4">
                                        {productInfo.product_type}
                                    </Badge>
                                    <p className="text-sm text-muted-foreground">
                                        Latest Version: {productInfo.latest_version}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="font-semibold mb-2">License Requirements</h4>
                                    <div className="flex items-center gap-2">
                                        {productInfo.requires_license ? (
                                            <>
                                                <Shield className="h-4 w-4 text-orange-600" />
                                                <span className="text-sm">License Required</span>
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                                <span className="text-sm">No License Required</span>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* System Requirements */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Monitor className="h-5 w-5" />
                                System Requirements
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div>
                                    <h4 className="font-semibold mb-2">Supported Platforms</h4>
                                    <div className="flex flex-wrap gap-2">
                                        {productInfo.supported_platforms.map((platform) => (
                                            <Badge key={platform} variant="outline">
                                                {platform}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <h4 className="font-semibold mb-2 flex items-center gap-2">
                                        <Cpu className="h-4 w-4" />
                                        Requirements
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        {productInfo.system_requirements}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Download Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Download className="h-5 w-5" />
                                Download Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <h4 className="font-semibold mb-2">Download Limit</h4>
                                    <p className="text-2xl font-bold text-primary">
                                        {productInfo.download_info.download_limit === -1
                                            ? 'Unlimited'
                                            : productInfo.download_info.download_limit
                                        }
                                    </p>
                                    <p className="text-sm text-muted-foreground">Downloads allowed</p>
                                </div>
                                <div>
                                    <h4 className="font-semibold mb-2">Access Duration</h4>
                                    <p className="text-2xl font-bold text-primary">
                                        {productInfo.download_info.download_expiry_days === -1
                                            ? 'Lifetime'
                                            : `${productInfo.download_info.download_expiry_days} days`
                                        }
                                    </p>
                                    <p className="text-sm text-muted-foreground">Access period</p>
                                </div>
                                <div>
                                    <h4 className="font-semibold mb-2">Auto Delivery</h4>
                                    <div className="flex items-center gap-2">
                                        {productInfo.download_info.auto_delivery ? (
                                            <>
                                                <CheckCircle className="h-6 w-6 text-green-600" />
                                                <div>
                                                    <p className="font-semibold">Enabled</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Instant access after purchase
                                                    </p>
                                                </div>
                                            </>
                                        ) : (
                                            <>
                                                <AlertTriangle className="h-6 w-6 text-orange-600" />
                                                <div>
                                                    <p className="font-semibold">Manual</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Manual processing required
                                                    </p>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
};