import * as React from 'react';
import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
    Download,
    CheckCircle,
    AlertTriangle,
    RefreshCw,
    X
} from 'lucide-react';
import {
    Card,
    CardContent,
    Button,
    Progress
} from '@/components/ui';
import { cn } from '@/lib/cn';

interface DownloadProgressProps {
    token: string;
    onComplete?: () => void;
    onError?: (error: string) => void;
    className?: string;
}

export const DownloadProgress: React.FC<DownloadProgressProps> = ({
                                                                      token,
                                                                      onComplete,
                                                                      onError,
                                                                      className
                                                                  }) => {
    const [progress, setProgress] = useState(0);
    const [status, setStatus] = useState<'downloading' | 'completed' | 'error' | 'cancelled'>('downloading');
    const [error, setError] = useState<string | null>(null);
    const [attemptId, setAttemptId] = useState<string | null>(null);
    const [downloadInfo, setDownloadInfo] = useState<any>(null);

    // Generate attempt ID when component mounts
    useEffect(() => {
        setAttemptId(Date.now().toString());
    }, []);

    // Fetch download info
    const fetchDownloadInfo = useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/digital/download/${token}/info`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            });

            if (response.ok) {
                const data = await response.json();
                setDownloadInfo(data.data);
            }
        } catch (err) {
            console.error('Failed to fetch download info:', err);
        }
    }, [token]);

    // Update progress on server
    const updateProgress = useCallback(async (progressValue: number) => {
        if (!attemptId) return;

        try {
            await fetch(`/api/v1/digital/download/${token}/progress/${attemptId}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    progress: progressValue,
                    status: status === 'completed' ? 'completed' : 'in_progress'
                })
            });
        } catch (err) {
            console.error('Failed to update progress:', err);
        }
    }, [token, attemptId, status]);

    // Simulate download progress (in real implementation, this would be based on actual download)
    useEffect(() => {
        if (status !== 'downloading') return;

        fetchDownloadInfo();

        const interval = setInterval(() => {
            setProgress(prev => {
                const newProgress = Math.min(prev + Math.random() * 15, 100);

                if (newProgress >= 100) {
                    setStatus('completed');
                    onComplete?.();
                    updateProgress(100);
                    clearInterval(interval);
                } else {
                    updateProgress(newProgress);
                }

                return newProgress;
            });
        }, 500);

        return () => clearInterval(interval);
    }, [status, fetchDownloadInfo, onComplete, updateProgress]);

    const handleCancel = () => {
        setStatus('cancelled');
        setProgress(0);
    };

    const handleRetry = () => {
        setStatus('downloading');
        setProgress(0);
        setError(null);
        setAttemptId(Date.now().toString());
    };

    if (status === 'cancelled') {
        return null;
    }

    return (
        <Card className={cn('border-l-4 border-l-blue-500', className)}>
            <CardContent className="p-4">
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                            {status === 'downloading' && (
                                <motion.div
                                    animate={{ rotate: 360 }}
                                    transition={{ duration: 2, repeat: Infinity, ease: "linear" }}
                                >
                                    <Download className="h-4 w-4 text-blue-600" />
                                </motion.div>
                            )}
                            {status === 'completed' && (
                                <CheckCircle className="h-4 w-4 text-green-600" />
                            )}
                            {status === 'error' && (
                                <AlertTriangle className="h-4 w-4 text-red-600" />
                            )}

                            <span className="text-sm font-medium">
                                {status === 'downloading' && 'Downloading...'}
                                {status === 'completed' && 'Download Complete'}
                                {status === 'error' && 'Download Failed'}
                            </span>
                        </div>

                        <div className="flex items-center space-x-2">
                            {status === 'downloading' && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={handleCancel}
                                >
                                    <X className="h-3 w-3" />
                                </Button>
                            )}
                            {status === 'error' && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={handleRetry}
                                >
                                    <RefreshCw className="h-3 w-3 mr-1" />
                                    Retry
                                </Button>
                            )}
                        </div>
                    </div>

                    {downloadInfo && (
                        <div className="text-xs text-muted-foreground">
                            {downloadInfo.file?.name} • {downloadInfo.file?.file_size}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Progress value={progress} className="h-2" />
                        <div className="flex justify-between text-xs text-muted-foreground">
                            <span>{Math.round(progress)}% complete</span>
                            {downloadInfo?.access && (
                                <span>
                                    {downloadInfo.access.downloads_remaining} downloads remaining
                                </span>
                            )}
                        </div>
                    </div>

                    {error && (
                        <div className="text-xs text-red-600 bg-red-50 p-2 rounded">
                            {error}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
};