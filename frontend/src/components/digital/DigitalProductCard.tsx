'use client';

import * as React from 'react';
import {
    Download,
    FileText,
    Calendar,
    CheckCircle,
    RefreshCw,
    Shield
} from 'lucide-react';
import {
    Card,
    CardContent,
    Button,
    Badge,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger
} from '@/components/ui';
import { DownloadProgress } from './DownloadProgress';
import { cn } from '@/lib/cn';

interface DigitalProductCardProps {
    downloadAccess: any; // Replace with proper type from your digital product types
    className?: string;
}

export const DigitalProductCard: React.FC<DigitalProductCardProps> = ({
                                                                          downloadAccess,
                                                                          className
                                                                      }) => {
    const [isDownloading, setIsDownloading] = React.useState(false);
    const [showDetails, setShowDetails] = React.useState(false);

    const handleDownload = async (fileUrl: string, fileName: string) => {
        setIsDownloading(true);
        try {
            // Simulate download process
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Create download link
            const link = document.createElement('a');
            link.href = fileUrl;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } catch (error) {
            console.error('Download failed:', error);
        } finally {
            setIsDownloading(false);
        }
    };

    const getStatusBadge = () => {
        const now = new Date();
        const expiresAt = downloadAccess.expires_at ? new Date(downloadAccess.expires_at) : null;

        if (expiresAt && expiresAt < now) {
            return <Badge variant="destructive">Expired</Badge>;
        } else if (expiresAt && expiresAt.getTime() - now.getTime() < 7 * 24 * 60 * 60 * 1000) {
            return <Badge variant="secondary">Expiring Soon</Badge>;
        } else {
            return <Badge variant="secondary">Active</Badge>;
        }
    };

    return (
        <Card className={cn('transition-all hover:shadow-md', className)}>
            <CardContent className="p-6">
                <div className="flex items-start justify-between mb-4">
                    <div className="flex-1">
                        <h3 className="text-lg font-semibold mb-2">
                            {downloadAccess.product.name}
                        </h3>
                        <div className="flex items-center gap-4 text-sm text-muted-foreground mb-3">
                            <div className="flex items-center gap-1">
                                <Download className="h-4 w-4" />
                                {downloadAccess.downloads_count || 0} downloads
                            </div>
                            <div className="flex items-center gap-1">
                                <Calendar className="h-4 w-4" />
                                Purchased {new Date(downloadAccess.created_at).toLocaleDateString()}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {getStatusBadge()}
                            {downloadAccess.is_lifetime && (
                                <Badge variant="outline" className="text-green-600">
                                    <CheckCircle className="h-3 w-3 mr-1" />
                                    Lifetime
                                </Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            onClick={() => handleDownload(downloadAccess.download_url, downloadAccess.product.name)}
                            disabled={isDownloading}
                            size="sm"
                        >
                            {isDownloading ? (
                                <>
                                    <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                                    Downloading...
                                </>
                            ) : (
                                <>
                                    <Download className="h-4 w-4 mr-2" />
                                    Download
                                </>
                            )}
                        </Button>

                        <Dialog open={showDetails} onOpenChange={setShowDetails}>
                            <DialogTrigger>
                                <Button variant="outline" size="sm">
                                    <FileText className="h-4 w-4 mr-2" />
                                    Details
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>{downloadAccess.product.name}</DialogTitle>
                                </DialogHeader>

                                <div className="space-y-6">
                                    <div>
                                        <h4 className="font-semibold mb-2">Product Information</h4>
                                        <div className="space-y-2 text-sm">
                                            <p><strong>Version:</strong> {downloadAccess.product.version || 'Latest'}</p>
                                            <p><strong>Size:</strong> {downloadAccess.file_size || 'Unknown'}</p>
                                            <p><strong>Format:</strong> {downloadAccess.file_format || 'Digital Download'}</p>
                                            <p><strong>License Type:</strong> {downloadAccess.license_type || 'Standard'}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 className="font-semibold mb-2">Download Information</h4>
                                        <div className="space-y-2 text-sm">
                                            <p><strong>Downloads Used:</strong> {downloadAccess.downloads_count || 0} / {downloadAccess.max_downloads || 'Unlimited'}</p>
                                            <p><strong>First Downloaded:</strong> {downloadAccess.first_downloaded_at ? new Date(downloadAccess.first_downloaded_at).toLocaleDateString() : 'Never'}</p>
                                            <p><strong>Last Downloaded:</strong> {downloadAccess.last_downloaded_at ? new Date(downloadAccess.last_downloaded_at).toLocaleDateString() : 'Never'}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 className="font-semibold mb-2">Access Information</h4>
                                        <div className="space-y-2 text-sm">
                                            <p><strong>Status:</strong> {downloadAccess.is_active ? 'Active' : 'Inactive'}</p>
                                            <p><strong>Expires:</strong> {downloadAccess.expires_at ? new Date(downloadAccess.expires_at).toLocaleDateString() : 'Never'}</p>
                                            <p><strong>Order ID:</strong> #{downloadAccess.order_id}</p>
                                        </div>
                                    </div>

                                    {downloadAccess.files && downloadAccess.files.length > 0 && (
                                        <div>
                                            <h4 className="font-semibold mb-2">Available Files</h4>
                                            <div className="space-y-2">
                                                {downloadAccess.files.map((file: any, index: number) => (
                                                    <div key={index} className="flex items-center justify-between p-2 bg-muted/50 rounded">
                                                        <div>
                                                            <p className="font-medium">{file.name}</p>
                                                            <p className="text-sm text-muted-foreground">{file.size}</p>
                                                        </div>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleDownload(file.url, file.name)}
                                                        >
                                                            <Download className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {downloadAccess.description && (
                    <p className="text-sm text-muted-foreground mb-4">
                        {downloadAccess.description}
                    </p>
                )}

                {/* Security Notice */}
                <div className="flex items-center gap-2 text-xs text-muted-foreground pt-4 border-t">
                    <Shield className="h-4 w-4" />
                    <span>Secure download protected by license verification</span>
                </div>

                {/* Download Progress */}
                {isDownloading && downloadAccess.download_token && (
                    <div className="mt-4">
                        <DownloadProgress
                            token={downloadAccess.download_token}
                            onComplete={() => setIsDownloading(false)}
                            onError={(error) => {
                                console.error('Download error:', error);
                                setIsDownloading(false);
                            }}
                        />
                    </div>
                )}
            </CardContent>
        </Card>
    );
};