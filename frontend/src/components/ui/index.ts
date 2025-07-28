// Updated frontend/src/components/ui/index.ts

// Core UI Components
export * from './button';
export * from './input';
export * from './card';
export * from './dialog';
export * from './loading';

// Loading Components
export { default as ProductCardSkeleton } from './loading/ProductCardSkeleton';

// Product Components
export * from '../product/ProductCard';
export * from '../product/ProductGrid';
export * from '../product/ProductFilters';
export * from '../product/search';
export * from '../product/detail/ProductDetail';

// Cart Components
export * from '../cart/CartItem';
export * from '../cart/CartSidebar';
export * from '../cart/MiniCartIndicator';

// Dashboard Components
export * from '../dashboard';

// frontend/src/components/dashboard/index.ts
export * from '../dashboard/UserProfile';
export * from '../dashboard/OrderHistory';
export * from '../dashboard/AddressManagement';

// frontend/src/components/dashboard/UserProfile.ts
export * from '../dashboard/UserProfileCard';
export * from '../dashboard/PasswordChangeDialog';
export * from '../dashboard/AccountSecurityCard';
export * from '../dashboard/PreferencesCard';

// frontend/src/components/dashboard/OrderHistory.ts
export * from '../dashboard/OrderCard';
export * from '../dashboard/OrderDetailsDialog';
export * from '../dashboard/OrderHistoryList';

// frontend/src/components/dashboard/AddressManagement.ts
export * from '../dashboard/AddressCard';
export * from '../dashboard/AddressFormDialog';
export * from '../dashboard/AddressManagement';

// Re-export utilities
export { cn } from '@/lib/cn';