interface DigitalProductCardProps {
    downloadAccess: any;
    className?: string;
}

export const DigitalProductCard: React.FC<DigitalProductCardProps> = ({
                                                                          downloadAccess,
                                                                          className
                                                                      }) => {
    const [isDownloading, setIsDownloading] = useState(false);
    const [showDetails, setShowDetails] = useState(false);

    const isExpired = new Date(downloadAccess.expires_at) < new Date();
    const isExpiringSoon = new Date(downloadAccess.expires_at) < new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);

    const handleDownload = async (fileId?: number) => {
        setIsDownloading(true);
        try {
            const response = await fetch(`/api/v1/digital/download/${downloadAccess.access_token}${fileId ? `?file_id=${fileId}` : ''}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = response.headers.get('Content-Disposition')?.split('filename=')[1]?.replace(/"/g, '') || 'download';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                throw new Error('Download failed');
            }
        } catch (error) {
            console.error('Download error:', error);
        } finally {
            setIsDownloading(false);
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
                        <p className="text-muted-foreground text-sm mb-3">
                            {downloadAccess.product.description}
                        </p>

                        <div className="flex items-center gap-4 text-sm">
                            <div className="flex items-center gap-1">
                                <Download className="h-4 w-4" />
                                <span>{downloadAccess.downloads_remaining || downloadAccess.download_limit} downloads left</span>
                            </div>
                            <div className="flex items-center gap-1">
                                <Calendar className="h-4 w-4" />
                                <span>Expires {new Date(downloadAccess.expires_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {isExpired ? (
                            <Badge variant="destructive">Expired</Badge>
                        ) : isExpiringSoon ? (
                            <Badge variant="secondary">Expiring Soon</Badge>
                        ) : (
                            <Badge variant="secondary">Active</Badge>
                        )}

                        {downloadAccess.status === 'active' && (
                            <Badge variant="outline" className="text-green-600">
                                <CheckCircle className="h-3 w-3 mr-1" />
                                Available
                            </Badge>
                        )}
                    </div>
                </div>

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Button
                            onClick={() => handleDownload()}
                            disabled={isExpired || isDownloading}
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
                                <div className="space-y-4">
                                    <div>
                                        <h4 className="font-semibold mb-2">Product Information</h4>
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p className="text-muted-foreground">Type</p>
                                                <p>{downloadAccess.product.product_type}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Version</p>
                                                <p>{downloadAccess.product.latest_version}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Platforms</p>
                                                <p>{downloadAccess.product.supported_platforms?.join(', ')}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">License Required</p>
                                                <p>{downloadAccess.product.requires_license ? 'Yes' : 'No'}</p>
                                            </div>
                                        </div>
                                    </div>

                                    {downloadAccess.product.files && (
                                        <div>
                                            <h4 className="font-semibold mb-2">Available Files</h4>
                                            <div className="space-y-2">
                                                {downloadAccess.product.files.map((file: any) => (
                                                    <div key={file.id} className="flex items-center justify-between p-3 border rounded-lg">
                                                        <div>
                                                            <p className="font-medium">{file.name}</p>
                                                            <p className="text-sm text-muted-foreground">
                                                                {file.file_size_formatted} • {file.file_type}
                                                            </p>
                                                        </div>
                                                        <Button
                                                            size="sm"
                                                            onClick={() => handleDownload(file.id)}
                                                            disabled={isExpired || isDownloading}
                                                        >
                                                            <Download className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div>
                                        <h4 className="font-semibold mb-2">Download Access</h4>
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p className="text-muted-foreground">Downloads Remaining</p>
                                                <p>{downloadAccess.downloads_remaining || downloadAccess.download_limit}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Expires At</p>
                                                <p>{new Date(downloadAccess.expires_at).toLocaleString()}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>

                    {downloadAccess.product.requires_license && (
                        <div className="flex items-center gap-1 text-sm text-muted-foreground">
                            <Shield className="h-4 w-4" />
                            <span>License Required</span>
                        </div>
                    )}
                </div>

                {isDownloading && (
                    <div className="mt-4">
                        <DownloadProgress
                            token={downloadAccess.access_token}
                            onComplete={() => setIsDownloading(false)}
                        />
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

export default DigitalLibrary;