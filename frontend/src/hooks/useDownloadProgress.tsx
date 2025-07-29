import { useState, useEffect, useCallback } from 'react';
import { DownloadAttempt } from '@/types/digital-products';

interface UseDownloadProgressReturn {
    progress: number;
    status: 'idle' | 'downloading' | 'completed' | 'error' | 'cancelled';
    error: string | null;
    startDownload: (token: string) => Promise<void>;
    cancelDownload: () => void;
    retryDownload: () => void;
}

export const useDownloadProgress = (): UseDownloadProgressReturn => {
    const [progress, setProgress] = useState(0);
    const [status, setStatus] = useState<'idle' | 'downloading' | 'completed' | 'error' | 'cancelled'>('idle');
    const [error, setError] = useState<string | null>(null);
    const [currentToken, setCurrentToken] = useState<string | null>(null);
    const [attemptId, setAttemptId] = useState<string | null>(null);

    const updateProgress = useCallback(async (token: string, attemptId: string, progressValue: number) => {
        try {
            await fetch(`/api/v1/digital/download/${token}/progress/${attemptId}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    progress: progressValue,
                    status: progressValue >= 100 ? 'completed' : 'in_progress'
                })
            });
        } catch (err) {
            console.error('Failed to update progress:', err);
        }
    }, []);

    const startDownload = useCallback(async (token: string) => {
        setCurrentToken(token);
        setAttemptId(Date.now().toString());
        setStatus('downloading');
        setProgress(0);
        setError(null);
    }, []);

    const cancelDownload = useCallback(() => {
        setStatus('cancelled');
        setProgress(0);
        setCurrentToken(null);
        setAttemptId(null);
    }, []);

    const retryDownload = useCallback(() => {
        if (currentToken) {
            startDownload(currentToken);
        }
    }, [currentToken, startDownload]);

    // Simulate download progress (in real implementation, this would be based on actual download events)
    useEffect(() => {
        if (status !== 'downloading' || !currentToken || !attemptId) return;

        const interval = setInterval(() => {
            setProgress(prev => {
                const newProgress = Math.min(prev + Math.random() * 10, 100);

                if (currentToken && attemptId) {
                    updateProgress(currentToken, attemptId, newProgress);
                }

                if (newProgress >= 100) {
                    setStatus('completed');
                    clearInterval(interval);
                }

                return newProgress;
            });
        }, 500);

        return () => clearInterval(interval);
    }, [status, currentToken, attemptId, updateProgress]);

    return {
        progress,
        status,
        error,
        startDownload,
        cancelDownload,
        retryDownload
    };
};
