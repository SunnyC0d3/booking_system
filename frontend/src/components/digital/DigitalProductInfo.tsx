'use client'

import * as React from 'react';
import { useState, useEffect } from 'react';
import { MainLayout } from '@/components/layout';
import {
    Monitor,
    HardDrive,
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
    Button
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
    const [productInfo, setProductInfo] = useState<ProductDigitalInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
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
                <div className="container mx-auto px-4 py-12">
                    <div className="max-w-4xl mx-auto">
                        <div className="animate-pulse space-y-6">
                            {Array.from({ length: 4 }).map((_, i) => (
                                <Card key={i}>
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
                    </div>
                </div>
            </MainLayout>
        );
    }

    if (error || !productInfo) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-12">
                    <div className="max-w-2xl mx-auto">
                        <Card className="border-destructive/20 bg-destructive/5">
                            <CardContent className="p-8 text-center">
                                <AlertTriangle className="h-12 w-12 text-destructive mx-auto mb-4" />
                                <h2 className="text-xl font-semibold text-destructive mb-2">
                                    Information Not Available
                                </h2>
                                <p className="text-muted-foreground">
                                    {error || 'This product does not have digital components.'}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </MainLayout>
        );
    }

    return (
        <MainLayout>
            <div className="container mx-auto px-4 py-12">
                <div className="max-w-4xl mx-auto space-y-6">
                    {/* Header */}
                    <div className="text-center space-y-2">
                        <h1 className="text-3xl font-bold">Digital Product Information</h1>
                        <p className="text-muted-foreground">
                            Technical specifications and download details
                        </p>
                    </div>

                    {/* Product Type & Licensing */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5" />
                                Product Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Product Type</p>
                                    <Badge variant="outline" className="mt-1">
                                        {productInfo.product_type}
                                    </Badge>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Current Version</p>
                                    <p className="font-medium">{productInfo.latest_version}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">License Required</p>
                                    <div className="flex items-center gap-1 mt-1">
                                        {productInfo.requires_license ? (
                                            <>
                                                <Shield className="h-4 w-4 text-amber-600" />
                                                <span className="text-sm">Yes</span>
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                                <span className="text-sm">No</span>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Supported Platforms */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Monitor className="h-5 w-5" />
                                Supported Platforms
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-2">
                                {productInfo.supported_platforms.map((platform) => (
                                    <Badge key={platform} variant="secondary">
                                        {platform}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* System Requirements */}
                    {productInfo.system_requirements && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Cpu className="h-5 w-5" />
                                    System Requirements
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="prose prose-sm max-w-none">
                                    {productInfo.system_requirements.split('\n').map((line, index) => (
                                        <p key={index}>{line}</p>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Download Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Download className="h-5 w-5" />
                                Download Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Download Limit</p>
                                    <p className="font-medium">{productInfo.download_info.download_limit} downloads</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Access Duration</p>
                                    <p className="font-medium">{productInfo.download_info.download_expiry_days} days</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Auto Delivery</p>
                                    <div className="flex items-center gap-1">
                                        {productInfo.download_info.auto_delivery ? (
                                            <>
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                                <span className="text-sm">Enabled</span>
                                            </>
                                        ) : (
                                            <>
                                                <AlertTriangle className="h-4 w-4 text-amber-600" />
                                                <span className="text-sm">Manual</span>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="p-4 bg-muted/50 rounded-lg">
                                <h4 className="font-semibold mb-2">Download Instructions</h4>
                                <ul className="text-sm space-y-1">
                                    <li>• Download links are sent via email after purchase</li>
                                    <li>• Each download link can be used {productInfo.download_info.download_limit} times</li>
                                    <li>• Links expire after {productInfo.download_info.download_expiry_days} days</li>
                                    {productInfo.requires_license && (
                                        <li>• License key will be provided for activation</li>
                                    )}
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
};