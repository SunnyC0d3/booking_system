import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import {
    DigitalProduct,
    DownloadAccess,
    LicenseKey,
    DigitalProductStatistics
} from '@/types/digital-products';

interface DigitalProductsState {
    // State
    digitalProducts: DigitalProduct[];
    downloadAccesses: DownloadAccess[];
    licenseKeys: LicenseKey[];
    statistics: DigitalProductStatistics | null;
    lastFetched: number | null;

    // Actions
    setDigitalProducts: (products: DigitalProduct[]) => void;
    setDownloadAccesses: (accesses: DownloadAccess[]) => void;
    setLicenseKeys: (keys: LicenseKey[]) => void;
    setStatistics: (stats: DigitalProductStatistics) => void;
    updateDownloadAccess: (accessId: number, updates: Partial<DownloadAccess>) => void;
    updateLicenseKey: (keyId: number, updates: Partial<LicenseKey>) => void;
    clearCache: () => void;
}

export const useDigitalProductsStore = create<DigitalProductsState>()(
    persist(
        (set, get) => ({
            // Initial state
            digitalProducts: [],
            downloadAccesses: [],
            licenseKeys: [],
            statistics: null,
            lastFetched: null,

            // Actions
            setDigitalProducts: (products) => set({
                digitalProducts: products,
                lastFetched: Date.now()
            }),

            setDownloadAccesses: (accesses) => set({
                downloadAccesses: accesses,
                lastFetched: Date.now()
            }),

            setLicenseKeys: (keys) => set({
                licenseKeys: keys,
                lastFetched: Date.now()
            }),

            setStatistics: (stats) => set({
                statistics: stats,
                lastFetched: Date.now()
            }),

            updateDownloadAccess: (accessId, updates) => set((state) => ({
                downloadAccesses: state.downloadAccesses.map(access =>
                    access.id === accessId ? { ...access, ...updates } : access
                )
            })),

            updateLicenseKey: (keyId, updates) => set((state) => ({
                licenseKeys: state.licenseKeys.map(key =>
                    key.id === keyId ? { ...key, ...updates } : key
                )
            })),

            clearCache: () => set({
                digitalProducts: [],
                downloadAccesses: [],
                licenseKeys: [],
                statistics: null,
                lastFetched: null
            })
        }),
        {
            name: 'digital-products-storage',
            partialize: (state) => ({
                digitalProducts: state.digitalProducts,
                downloadAccesses: state.downloadAccesses,
                licenseKeys: state.licenseKeys,
                statistics: state.statistics,
                lastFetched: state.lastFetched
            })
        }
    )
);