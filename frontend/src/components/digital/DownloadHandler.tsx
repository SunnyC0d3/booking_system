import * as React from 'react';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import {
    Download,
    AlertTriangle,
    CheckCircle,
    Clock,
    FileText,
    Shield,
    ArrowLeft,
    ExternalLink
} from 'lucide-react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Button,
    Badge,
    Alert,
    AlertDescription
} from '@/components/ui';
import { DownloadProgress } from './DownloadProgress';
import { MainLayout } from '@/components/layout';
import { cn } from '@/lib/cn';

interface DownloadHandlerProps {
    token: string;
}

interface DownloadInfo {
    file: {
        id: number;
        name: string;
        file_size: string;
        file_type: string;
        version: string;
        description: string;
    };
    access: {
        downloads_remaining: number;
        expires_at: string;
        status: string;
    };
    product: {
        id: number;
        name: string;
        latest_version: string;
    };
}

export const DownloadHandler: React.FC<DownloadHandlerProps> = ({ token }) => {
    const router = useRouter();
    const [downloadInfo, setDownloadInfo] = useState<DownloadInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [isDownloading, setIsDownloading] = useState(false);
    const [requiresAuth, setRequiresAuth] = useState(false);

    useEffect(() => {
        fetchDownloadInfo();
    }, [token]);

    const fetchDownloadInfo = async () => {
        try {
            setLoading(true);
            setError(null);

            // First try to get download info (might work for guest or authenticated users)
            let response = await fetch(`/api/v1/digital/download/${token}/info`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            });

            if (response.status === 401) {
                // Try guest download info endpoint
                response = await fetch(`/api/v1/download-info/${token}`);
                if (response.ok) {
                    setRequiresAuth(true);
                } else {
                    throw new Error('Authentication required');
                }
            }

            if (!response.ok) {
                throw new Error('Download not found or expired');
            }

            const data = await response.json();
            setDownloadInfo(data.data);

        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load download information');
        } finally {
            setLoading(false);
        }
    };

    const handleDownload = async () => {
        if (!downloadInfo || requiresAuth) return;

        setIsDownloading(true);
        try {
            const response = await fetch(`/api/v1/digital/download/${token}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            });

            if (!response.ok) {
                throw new Error('Download failed');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = response.headers.get('Content-Disposition')?.split('filename=')[1]?.replace(/"/g, '') || 'download';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            // Refresh download info to update remaining downloads
            await fetchDownloadInfo();

        } catch (err) {
            setError(err instanceof Error ? err.message : 'Download failed');
        } finally {
            setIsDownloading(false);
        }
    };

    const isExpired = downloadInfo && new Date(downloadInfo.access.expires_at) < new Date();
    const canDownload = downloadInfo &&
        downloadInfo.access.status === 'active' &&
        !isExpired &&
        downloadInfo.access.downloads_remaining > 0 &&
        !requiresAuth;

    if (loading) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-12">
                    <div className="max-w-2xl mx-auto">
                        <Card>
                            <CardContent className="p-8 text-center">
                                <motion.div
                                    animate={{ rotate: 360 }}
                                    transition={{ duration: 2, repeat: Infinity, ease: "linear" }}
                                    className="inline-block mb-4"
                                >
                                    <Download className="h-12 w-12 text-primary" />
                                </motion.div>
                                <h2 className="text-xl font-semibold mb-2">Loading Download...</h2>
                                <p className="text-muted-foreground">
                                    Preparing your download information
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </MainLayout>
        );
    }

    if (error) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-12">
                    <div className="max-w-2xl mx-auto">
                        <Card className="border-destructive/20 bg-destructive/5">
                            <CardContent className="p-8 text-center">
                                <AlertTriangle className="h-12 w-12 text-destructive mx-auto mb-4" />
                                <h2 className="text-xl font-semibold text-destructive mb-2">
                                    Download Error
                                </h2>
                                <p className="text-muted-foreground mb-6">{error}</p>
                                <div className="flex gap-2 justify-center">
                                    <Button onClick={() => router.back()} variant="outline">
                                        <ArrowLeft className="h-4 w-4 mr-2" />
                                        Go Back
                                    </Button>
                                    <Button onClick={fetchDownloadInfo}>
                                        Try Again
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </MainLayout>
        );
    }

    if (requiresAuth) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-12">
                    <div className="max-w-2xl mx-auto">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    Authentication Required
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {downloadInfo && (
                                    <div className="p-4 bg-muted/50 rounded-lg">
                                        <h3 className="font-semibold mb-2">{downloadInfo.product.name}</h3>
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p className="text-muted-foreground">Downloads Remaining</p>
                                                <p>{downloadInfo.access.downloads_remaining}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Expires</p>
                                                <p>{new Date(downloadInfo.access.expires_at).toLocaleDateString()}</p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <Alert>
                                    <AlertTriangle className="h-4 w-4" />
                                    <AlertDescription>
                                        You need to be logged in to download this file. Please sign in to continue.
                                    </AlertDescription>
                                </Alert>

                                <div className="flex gap-2">
                                    <Button onClick={() => router.push('/login')} className="flex-1">
                                        Sign In
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() => router.push('/register')}
                                        className="flex-1"
                                    >
                                        Create Account
                                    </Button>
                                </div>
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
                <div className="max-w-2xl mx-auto space-y-6">
                    {/* Download Info Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                {downloadInfo?.product.name}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* File Information */}
                            <div className="p-4 bg-muted/50 rounded-lg">
                                <h3 className="font-semibold mb-3">{downloadInfo?.file.name}</h3>
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-muted-foreground">File Size</p>
                                        <p>{downloadInfo?.file.file_size}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Version</p>
                                        <p>{downloadInfo?.file.version}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">File Type</p>
                                        <p>{downloadInfo?.file.file_type.toUpperCase()}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Downloads Left</p>
                                        <p>{downloadInfo?.access.downloads_remaining}</p>
                                    </div>
                                </div>
                                {downloadInfo?.file.description && (
                                    <div className="mt-3">
                                        <p className="text-muted-foreground text-sm">Description</p>
                                        <p className="text-sm">{downloadInfo.file.description}</p>
                                    </div>
                                )}
                            </div>

                            {/* Access Information */}
                            <div className="flex items-center justify-between p-4 border rounded-lg">
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">Download Access</p>
                                        <p className="text-xs text-muted-foreground">
                                            Expires {new Date(downloadInfo?.access.expires_at || '').toLocaleDateString()}
                                        </p>
                                    </div>
                                </div>
                                <Badge variant={
                                    isExpired ? 'destructive' :
                                        downloadInfo?.access.status === 'active' ? 'default' : 'secondary'
                                }>
                                    {isExpired ? 'Expired' : downloadInfo?.access.status}
                                </Badge>
                            </div>

                            {/* Download Actions */}
                            <div className="space-y-4">
                                {isExpired && (
                                    <Alert>
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertDescription>
                                            This download link has expired. Please contact support if you need assistance.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {!canDownload && !isExpired && (
                                    <Alert>
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertDescription>
                                            {downloadInfo?.access.downloads_remaining === 0
                                                ? 'You have used all available downloads for this product.'
                                                : 'This download is not currently available.'
                                            }
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="flex gap-2">
                                    <Button
                                        onClick={handleDownload}
                                        disabled={!canDownload || isDownloading}
                                        size="lg"
                                        className="flex-1"
                                    >
                                        {isDownloading ? (
                                            <>
                                                <motion.div
                                                    animate={{ rotate: 360 }}
                                                    transition={{ duration: 2, repeat: Infinity, ease: "linear" }}
                                                    className="mr-2"
                                                >
                                                    <Download className="h-4 w-4" />
                                                </motion.div>
                                                Downloading...
                                            </>
                                        ) : (
                                            <>
                                                <Download className="h-4 w-4 mr-2" />
                                                Download Now
                                            </>
                                        )}
                                    </Button>

                                    <Button
                                        variant="outline"
                                        onClick={() => router.push('/account/digital-library')}
                                    >
                                        <ExternalLink className="h-4 w-4 mr-2" />
                                        My Library
                                    </Button>
                                </div>
                            </div>

                            {/* Download Progress */}
                            {isDownloading && (
                                <DownloadProgress
                                    token={token}
                                    onComplete={() => setIsDownloading(false)}
                                />
                            )}
                        </CardContent>
                    </Card>

                    {/* Help Card */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="font-semibold mb-3">Need Help?</h3>
                            <div className="space-y-2 text-sm">
                                <p>• Make sure you have sufficient storage space for the download</p>
                                <p>• Check your internet connection if the download is slow</p>
                                <p>• Contact support if you encounter any issues</p>
                            </div>
                            <Button variant="outline" size="sm" className="mt-4">
                                Contact Support
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
};