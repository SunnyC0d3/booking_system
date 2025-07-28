
// Layout Components
export { default as Header } from './Header';
export { default as Footer } from './Footer';
export { default as AuthLayout } from './AuthLayout';
export {
    default as MainLayout,
    DashboardLayout,
    ProductLayout,
    ContentLayout,
    CheckoutLayout,
} from './MainLayout';

// Navigation Components
export {
    default as Breadcrumbs,
    BreadcrumbContainer,
    useBreadcrumbs,
    type BreadcrumbItem,
} from './Breadcrumbs';