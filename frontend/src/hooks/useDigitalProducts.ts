import { useState, useEffect, useCallback } from 'react';
import {
    DigitalProduct,
    DownloadAccess,
    LicenseKey,
    DigitalProductStatistics,
    DigitalLibraryResponse
} from '@/types/digital-products';

interface UseDigitalProductsReturn {
    digitalProducts: DigitalProduct[];
    downloadAccesses: DownloadAccess[];
    licenseKeys: LicenseKey[];
    statistics: DigitalProductStatistics | null;
    loading: boolean;
    error: string | null;
    refreshLibrary: () => Promise<void>;
    downloadFile: (token: string, fileId?: number) => Promise<void>;
    validateLicense: (licenseKey: string, productId?: number) => Promise<boolean>;
}

export const useDigitalProducts = (): UseDigitalProductsReturn => {
    const [digitalProducts, setDigitalProducts] = useState<DigitalProduct[]>([]);
    const [downloadAccesses, setDownloadAccesses] = useState<DownloadAccess[]>([]);
    const [licenseKeys, setLicenseKeys] = useState<LicenseKey[]>([]);
    const [statistics, setStatistics] = useState<DigitalProductStatistics | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const getAuthToken = () => localStorage.getItem('auth_token');

    const apiRequest = async (endpoint: string, options: RequestInit = {}) => {
        const token = getAuthToken();
        const response = await fetch(`/api/v1${endpoint}`, {
            ...options,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                ...options.headers,
            },
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Network error' }));
            throw new Error(errorData.message || `HTTP ${response.status}`);
        }

        return response.json();
    };

    const fetchDigitalLibrary = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);

            // Fetch user's digital library
            const libraryResponse: DigitalLibraryResponse = await apiRequest('/my-digital-products');

            setDownloadAccesses(libraryResponse.data.download_accesses || []);
            setLicenseKeys(libraryResponse.data.license_keys || []);
            setStatistics(libraryResponse.data.statistics || null);

            // Extract unique products from download accesses
            const products = libraryResponse.data.download_accesses
                .map(access => access.product)
                .filter((product, index, self) =>
                    index === self.findIndex(p => p.id === product.id)
                );
            setDigitalProducts(products);

        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load digital library');
            console.error('Digital library fetch error:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    const refreshLibrary = useCallback(async () => {
        await fetchDigitalLibrary();
    }, [fetchDigitalLibrary]);

    const downloadFile = useCallback(async (token: string, fileId?: number) => {
        try {
            const url = `/api/v1/digital/download/${token}${fileId ? `?file_id=${fileId}` : ''}`;
            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${getAuthToken()}`
                }
            });

            if (!response.ok) {
                throw new Error('Download failed');
            }

            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;

            // Get filename from response headers
            const contentDisposition = response.headers.get('Content-Disposition');
            const filename = contentDisposition
                ?.split('filename=')[1]
                ?.replace(/"/g, '') || 'download';

            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(downloadUrl);
            document.body.removeChild(a);

            // Refresh library to update download counts
            await refreshLibrary();

        } catch (err) {
            console.error('Download error:', err);
            throw err;
        }
    }, [refreshLibrary]);

    const validateLicense = useCallback(async (licenseKey: string, productId?: number): Promise<boolean> => {
        try {
            const response = await fetch('/api/v1/license/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    license_key: licenseKey,
                    product_id: productId
                })
            });

            const data = await response.json();
            return data.data?.valid || false;

        } catch (err) {
            console.error('License validation error:', err);
            return false;
        }
    }, []);

    useEffect(() => {
        fetchDigitalLibrary();
    }, [fetchDigitalLibrary]);

    return {
        digitalProducts,
        downloadAccesses,
        licenseKeys,
        statistics,
        loading,
        error,
        refreshLibrary,
        downloadFile,
        validateLicense
    };
};
