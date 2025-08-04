import * as React from 'react';
import { Metadata } from 'next';
import { useRouter, useSearchParams } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Search,
    Filter,
    X,
    SlidersHorizontal,
    Grid3X3,
    List,
    ArrowUpDown,
    Star,
    Package,
    Tag,
    Palette,
    DollarSign,
    Calendar,
    TrendingUp,
    Clock,
    Eye,
    Sparkles,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Badge,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    Checkbox,
    RadioGroup,
    RadioGroupItem,
    Label,
    Slider,
} from '@/components/ui';
import { MainLayout } from '@/components/layout';
import { ProductGrid } from '@/components/product/ProductGrid';
import { ProductCard } from '@/components/product/ProductCard';
import { useProductStore } from '@/stores/productStore';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

export const metadata: Metadata = {
    title: 'Search Products | Creative Business',
    description: 'Find the perfect products with our advanced search and filtering options.',
};

// Search suggestion chips
const popularSearches = [
    'Wedding invitations',
    'Custom labels',
    'Gift tags',
    'Birthday cards',
    'Business stickers',
    'Thank you cards',
    'Address labels',
    'Holiday decorations',
];

// Sort options
const sortOptions = [
    { value: 'relevance', label: 'Best Match', icon: TrendingUp },
    { value: 'name', label: 'Name A-Z', icon: ArrowUpDown },
    { value: '-name', label: 'Name Z-A', icon: ArrowUpDown },
    { value: 'price', label: 'Price: Low to High', icon: DollarSign },
    { value: '-price', label: 'Price: High to Low', icon: DollarSign },
    { value: '-created_at', label: 'Newest First', icon: Calendar },
    { value: 'created_at', label: 'Oldest First', icon: Calendar },
    { value: '-rating', label: 'Highest Rated', icon: Star },
    { value: 'rating', label: 'Lowest Rated', icon: Star },
];

// Price ranges
const priceRanges = [
    { min: 0, max: 25, label: 'Under £25' },
    { min: 25, max: 50, label: '£25 - £50' },
    { min: 50, max: 100, label: '£50 - £100' },
    { min: 100, max: 200, label: '£100 - £200' },
    { min: 200, max: 500, label: '£200 - £500' },
    { min: 500, max: null, label: 'Over £500' },
];

// Categories (mock data - would come from API)
const categories = [
    { id: 1, name: 'Custom Labels', count: 245 },
    { id: 2, name: 'Wedding Invitations', count: 189 },
    { id: 3, name: 'Gift Tags', count: 156 },
    { id: 4, name: 'Stickers & Decals', count: 134 },
    { id: 5, name: 'Greeting Cards', count: 98 },
    { id: 6, name: 'Packaging Inserts', count: 67 },
    { id: 7, name: 'Business Cards', count: 45 },
    { id: 8, name: 'Thank You Cards', count: 34 },
];

// Availability options
const availabilityOptions = [
    { value: 'in_stock', label: 'In Stock', count: 892 },
    { value: 'low_stock', label: 'Low Stock', count: 45 },
    { value: 'out_of_stock', label: 'Out of Stock', count: 23 },
];

// Brands/Vendors (mock data)
const brands = [
    { id: 1, name: 'Creative Studio', count: 156 },
    { id: 2, name: 'Premium Print Co.', count: 134 },
    { id: 3, name: 'Design Masters', count: 89 },
    { id: 4, name: 'Luxury Labels', count: 67 },
    { id: 5, name: 'Custom Creations', count: 45 },
];

// Attributes (mock data)
const attributes = [
    {
        name: 'Material',
        values: [
            { value: 'paper', label: 'Paper', count: 234 },
            { value: 'vinyl', label: 'Vinyl', count: 167 },
            { value: 'cardstock', label: 'Cardstock', count: 145 },
            { value: 'fabric', label: 'Fabric', count: 89 },
            { value: 'plastic', label: 'Plastic', count: 56 },
        ],
    },
    {
        name: 'Size',
        values: [
            { value: 'small', label: 'Small', count: 189 },
            { value: 'medium', label: 'Medium', count: 234 },
            { value: 'large', label: 'Large', count: 156 },
            { value: 'extra-large', label: 'Extra Large', count: 67 },
        ],
    },
    {
        name: 'Finish',
        values: [
            { value: 'matte', label: 'Matte', count: 203 },
            { value: 'glossy', label: 'Glossy', count: 189 },
            { value: 'satin', label: 'Satin', count: 134 },
            { value: 'textured', label: 'Textured', count: 78 },
        ],
    },
];

interface SearchFilters {
    query: string;
    categories: number[];
    priceMin: number;
    priceMax: number;
    availability: string[];
    brands: number[];
    attributes: Record<string, string[]>;
    rating: number | null;
    sort: string;
}

export default function SearchPage() {
    const router = useRouter();
    const searchParams = useSearchParams();
    const { searchProducts, searchResults, isLoading, clearError } = useProductStore();

    // Search state
    const [filters, setFilters] = React.useState<SearchFilters>({
        query: searchParams.get('q') || '',
        categories: [],
        priceMin: 0,
        priceMax: 1000,
        availability: [],
        brands: [],
        attributes: {},
        rating: null,
        sort: 'relevance',
    });

    // UI state
    const [isFiltersOpen, setIsFiltersOpen] = React.useState(false);
    const [viewMode, setViewMode] = React.useState<'grid' | 'list'>('grid');
    const [showSuggestions, setShowSuggestions] = React.useState(false);
    const [searchInput, setSearchInput] = React.useState(filters.query);

    // Initialize search from URL params
    React.useEffect(() => {
        const query = searchParams.get('q');
        const category = searchParams.get('category');
        const priceMin = searchParams.get('price_min');
        const priceMax = searchParams.get('price_max');
        const sort = searchParams.get('sort');

        const initialFilters = {
            ...filters,
            query: query || '',
            categories: category ? [parseInt(category)] : [],
            priceMin: priceMin ? parseInt(priceMin) : 0,
            priceMax: priceMax ? parseInt(priceMax) : 1000,
            sort: sort || 'relevance',
        };

        setFilters(initialFilters);
        setSearchInput(query || '');

        // Perform initial search
        if (query || category) {
            performSearch(initialFilters);
        }
    }, [searchParams]);

    const performSearch = async (searchFilters: SearchFilters) => {
        try {
            clearError();

            const params = {
                q: searchFilters.query || undefined,
                category: searchFilters.categories.length > 0 ? searchFilters.categories.join(',') : undefined,
                price_min: searchFilters.priceMin > 0 ? searchFilters.priceMin : undefined,
                price_max: searchFilters.priceMax < 1000 ? searchFilters.priceMax : undefined,
                availability: searchFilters.availability.length > 0 ? searchFilters.availability.join(',') : undefined,
                brands: searchFilters.brands.length > 0 ? searchFilters.brands.join(',') : undefined,
                rating: searchFilters.rating || undefined,
                sort: searchFilters.sort !== 'relevance' ? searchFilters.sort : undefined,
                per_page: 24,
            };

            // Add attributes to params
            Object.entries(searchFilters.attributes).forEach(([key, values]) => {
                if (values.length > 0) {
                    (params as any)[`attributes[${key}]`] = values.join(',');
                }
            });

            await searchProducts(params);

            // Update URL
            const urlParams = new URLSearchParams();
            Object.entries(params).forEach(([key, value]) => {
                if (value !== undefined && value !== '') {
                    urlParams.set(key, value.toString());
                }
            });

            const newUrl = urlParams.toString() ? `/search?${urlParams.toString()}` : '/search';
            router.replace(newUrl, { scroll: false });

        } catch (error) {
            toast.error('Search failed. Please try again.');
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const newFilters = { ...filters, query: searchInput };
        setFilters(newFilters);
        performSearch(newFilters);
        setShowSuggestions(false);
    };

    const handleFilterChange = (key: keyof SearchFilters, value: any) => {
        const newFilters = { ...filters, [key]: value };
        setFilters(newFilters);
        performSearch(newFilters);
    };

    const handleCategoryToggle = (categoryId: number) => {
        const newCategories = filters.categories.includes(categoryId)
            ? filters.categories.filter(id => id !== categoryId)
            : [...filters.categories, categoryId];
        handleFilterChange('categories', newCategories);
    };

    const handleAvailabilityToggle = (availability: string) => {
        const newAvailability = filters.availability.includes(availability)
            ? filters.availability.filter(a => a !== availability)
            : [...filters.availability, availability];
        handleFilterChange('availability', newAvailability);
    };

    const handleBrandToggle = (brandId: number) => {
        const newBrands = filters.brands.includes(brandId)
            ? filters.brands.filter(id => id !== brandId)
            : [...filters.brands, brandId];
        handleFilterChange('brands', newBrands);
    };

    const handleAttributeToggle = (attributeName: string, value: string) => {
        const currentValues = filters.attributes[attributeName] || [];
        const newValues = currentValues.includes(value)
            ? currentValues.filter(v => v !== value)
            : [...currentValues, value];

        const newAttributes = {
            ...filters.attributes,
            [attributeName]: newValues,
        };

        if (newValues.length === 0) {
            delete newAttributes[attributeName];
        }

        handleFilterChange('attributes', newAttributes);
    };

    const clearAllFilters = () => {
        const clearedFilters = {
            query: '',
            categories: [],
            priceMin: 0,
            priceMax: 1000,
            availability: [],
            brands: [],
            attributes: {},
            rating: null,
            sort: 'relevance',
        };
        setFilters(clearedFilters);
        setSearchInput('');
        performSearch(clearedFilters);
    };

    const getActiveFiltersCount = () => {
        return (
            filters.categories.length +
            filters.availability.length +
            filters.brands.length +
            Object.values(filters.attributes).flat().length +
            (filters.priceMin > 0 || filters.priceMax < 1000 ? 1 : 0) +
            (filters.rating ? 1 : 0)
        );
    };

    const handleSuggestionClick = (suggestion: string) => {
        setSearchInput(suggestion);
        const newFilters = { ...filters, query: suggestion };
        setFilters(newFilters);
        performSearch(newFilters);
        setShowSuggestions(false);
    };

    const products = searchResults?.data || [];
    const pagination = searchResults?.meta?.pagination;
    const activeFiltersCount = getActiveFiltersCount();

    return (
        <MainLayout>
            <div className="container mx-auto px-4 py-8">
                {/* Search Header */}
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6 }}
                    className="mb-8"
                >
                    <div className="text-center mb-8">
                        <h1 className="text-3xl lg:text-4xl font-bold text-foreground mb-4">
                            {filters.query ? `Search Results for "${filters.query}"` : 'Search Products'}
                        </h1>
                        <p className="text-muted-foreground">
                            {pagination ? (
                                `Found ${pagination.total} products`
                            ) : (
                                'Use our advanced search to find exactly what you need'
                            )}
                        </p>
                    </div>

                    {/* Search Bar */}
                    <Card className="max-w-4xl mx-auto">
                        <CardContent className="p-6">
                            <form onSubmit={handleSearch} className="relative">
                                <div className="relative">
                                    <Input
                                        type="text"
                                        placeholder="Search for products, categories, or keywords..."
                                        value={searchInput}
                                        onChange={(e) => {
                                            setSearchInput(e.target.value);
                                            setShowSuggestions(e.target.value.length > 0);
                                        }}
                                        onFocus={() => setShowSuggestions(searchInput.length > 0)}
                                        className="pr-12 text-lg h-14"
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        className="absolute right-2 top-2 h-10 px-4"
                                    >
                                        <Search className="h-4 w-4" />
                                    </Button>
                                </div>

                                {/* Search Suggestions */}
                                <AnimatePresence>
                                    {showSuggestions && (
                                        <motion.div
                                            initial={{ opacity: 0, y: -10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -10 }}
                                            transition={{ duration: 0.2 }}
                                            className="absolute top-full left-0 right-0 z-50 mt-2 bg-background border rounded-lg shadow-lg"
                                        >
                                            <div className="p-4">
                                                <h3 className="text-sm font-medium text-muted-foreground mb-3">
                                                    Popular Searches
                                                </h3>
                                                <div className="flex flex-wrap gap-2">
                                                    {popularSearches.map((suggestion) => (
                                                        <Button
                                                            key={suggestion}
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleSuggestionClick(suggestion)}
                                                            className="h-8 text-xs"
                                                        >
                                                            {suggestion}
                                                        </Button>
                                                    ))}
                                                </div>
                                            </div>
                                        </motion.div>
                                    )}
                                </AnimatePresence>
                            </form>
                        </CardContent>
                    </Card>
                </motion.div>

                {/* Filters and Results */}
                <div className="grid lg:grid-cols-4 gap-8">
                    {/* Filters Sidebar */}
                    <motion.div
                        initial={{ opacity: 0, x: -20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.6, delay: 0.2 }}
                        className="lg:col-span-1"
                    >
                        {/* Mobile Filter Toggle */}
                        <div className="lg:hidden mb-4">
                            <Dialog open={isFiltersOpen} onOpenChange={setIsFiltersOpen}>
                                <DialogTrigger>
                                    <Button variant="outline" className="w-full">
                                        <SlidersHorizontal className="mr-2 h-4 w-4" />
                                        Filters
                                        {activeFiltersCount > 0 && (
                                            <Badge className="ml-2" variant="secondary">
                                                {activeFiltersCount}
                                            </Badge>
                                        )}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-w-md max-h-[80vh] overflow-y-auto">
                                    <DialogHeader>
                                        <DialogTitle>Filters</DialogTitle>
                                    </DialogHeader>
                                    <FiltersContent
                                        filters={filters}
                                        onCategoryToggle={handleCategoryToggle}
                                        onAvailabilityToggle={handleAvailabilityToggle}
                                        onBrandToggle={handleBrandToggle}
                                        onAttributeToggle={handleAttributeToggle}
                                        onPriceChange={(min, max) => {
                                            const newFilters = { ...filters, priceMin: min, priceMax: max };
                                            setFilters(newFilters);
                                            performSearch(newFilters);
                                        }}
                                        onRatingChange={(rating) => handleFilterChange('rating', rating)}
                                        onClearAll={clearAllFilters}
                                        activeFiltersCount={activeFiltersCount}
                                    />
                                </DialogContent>
                            </Dialog>
                        </div>

                        {/* Desktop Filters */}
                        <div className="hidden lg:block">
                            <FiltersContent
                                filters={filters}
                                onCategoryToggle={handleCategoryToggle}
                                onAvailabilityToggle={handleAvailabilityToggle}
                                onBrandToggle={handleBrandToggle}
                                onAttributeToggle={handleAttributeToggle}
                                onPriceChange={(min, max) => {
                                    const newFilters = { ...filters, priceMin: min, priceMax: max };
                                    setFilters(newFilters);
                                    performSearch(newFilters);
                                }}
                                onRatingChange={(rating) => handleFilterChange('rating', rating)}
                                onClearAll={clearAllFilters}
                                activeFiltersCount={activeFiltersCount}
                            />
                        </div>
                    </motion.div>

                    {/* Results */}
                    <motion.div
                        initial={{ opacity: 0, x: 20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.6, delay: 0.4 }}
                        className="lg:col-span-3"
                    >
                        {/* Results Header */}
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between mb-6">
                            <div className="flex items-center gap-4 mb-4 sm:mb-0">
                                {pagination && (
                                    <p className="text-muted-foreground">
                                        Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total} results
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-4">
                                {/* View Mode Toggle */}
                                <div className="flex items-center border rounded-lg p-1">
                                    <Button
                                        variant={viewMode === 'grid' ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('grid')}
                                        className="h-8 px-3"
                                    >
                                        <Grid3X3 className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'list' ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('list')}
                                        className="h-8 px-3"
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                </div>

                                {/* Sort Dropdown */}
                                <select
                                    value={filters.sort}
                                    onChange={(e) => handleFilterChange('sort', e.target.value)}
                                    className="px-3 py-2 border border-input rounded-lg bg-background text-sm"
                                >
                                    {sortOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Active Filters */}
                        {activeFiltersCount > 0 && (
                            <div className="mb-6">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm text-muted-foreground">Active filters:</span>
                                    {filters.categories.map((categoryId) => {
                                        const category = categories.find(c => c.id === categoryId);
                                        return category && (
                                            <Badge
                                                key={categoryId}
                                                variant="secondary"
                                                className="cursor-pointer"
                                                onClick={() => handleCategoryToggle(categoryId)}
                                            >
                                                {category.name}
                                                <X className="ml-1 h-3 w-3" />
                                            </Badge>
                                        );
                                    })}
                                    {(filters.priceMin > 0 || filters.priceMax < 1000) && (
                                        <Badge
                                            variant="secondary"
                                            className="cursor-pointer"
                                            onClick={() => handleFilterChange('priceMin', 0) && handleFilterChange('priceMax', 1000)}
                                        >
                                            £{filters.priceMin} - £{filters.priceMax}
                                            <X className="ml-1 h-3 w-3" />
                                        </Badge>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearAllFilters}
                                        className="h-6 px-2 text-xs"
                                    >
                                        Clear all
                                    </Button>
                                </div>
                            </div>
                        )}

                        {/* Results Content */}
                        {isLoading ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                {[...Array(9)].map((_, i) => (
                                    <Card key={i} className="overflow-hidden">
                                        <div className="aspect-square bg-muted animate-pulse" />
                                        <CardContent className="p-4">
                                            <div className="space-y-3">
                                                <div className="h-4 bg-muted rounded animate-pulse" />
                                                <div className="h-3 bg-muted rounded w-2/3 animate-pulse" />
                                                <div className="h-6 bg-muted rounded w-1/3 animate-pulse" />
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        ) : products.length > 0 ? (
                            <div className={cn(
                                viewMode === 'grid'
                                    ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6'
                                    : 'space-y-4'
                            )}>
                                {products.map((product: any) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        showWishlist={true}
                                        showCompare={true}
                                        showQuickAdd={true}
                                        layout={viewMode}
                                    />
                                ))}
                            </div>
                        ) : (
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6 }}
                                className="text-center py-16"
                            >
                                <div className="max-w-md mx-auto">
                                    <Package className="h-16 w-16 text-muted-foreground mx-auto mb-6" />
                                    <h3 className="text-xl font-semibold text-foreground mb-4">
                                        No Products Found
                                    </h3>
                                    <p className="text-muted-foreground mb-8">
                                        We couldn't find any products matching your search criteria.
                                        Try adjusting your filters or search terms.
                                    </p>
                                    <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                        <Button onClick={clearAllFilters}>
                                            Clear All Filters
                                        </Button>
                                        <Button variant="outline">
                                            <a href="/products">Browse All Products</a>
                                        </Button>
                                    </div>
                                </div>
                            </motion.div>
                        )}

                        {/* Pagination would go here */}
                        {pagination && pagination.last_page > 1 && (
                            <div className="mt-12 flex justify-center">
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        disabled={pagination.current_page <= 1}
                                        onClick={() => {
                                            // Handle previous page
                                        }}
                                    >
                                        Previous
                                    </Button>
                                    <span className="text-sm text-muted-foreground px-4">
                                        Page {pagination.current_page} of {pagination.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        disabled={pagination.current_page >= pagination.last_page}
                                        onClick={() => {
                                            // Handle next page
                                        }}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </motion.div>
                </div>
            </div>
        </MainLayout>
    );
}

// Filters Content Component
interface FiltersContentProps {
    filters: SearchFilters;
    onCategoryToggle: (categoryId: number) => void;
    onAvailabilityToggle: (availability: string) => void;
    onBrandToggle: (brandId: number) => void;
    onAttributeToggle: (attributeName: string, value: string) => void;
    onPriceChange: (min: number, max: number) => void;
    onRatingChange: (rating: number | null) => void;
    onClearAll: () => void;
    activeFiltersCount: number;
}

const FiltersContent: React.FC<FiltersContentProps> = ({
                                                           filters,
                                                           onCategoryToggle,
                                                           onAvailabilityToggle,
                                                           onBrandToggle,
                                                           onAttributeToggle,
                                                           onPriceChange,
                                                           onRatingChange,
                                                           onClearAll,
                                                           activeFiltersCount,
                                                       }) => {
    return (
        <div className="space-y-6">
            {/* Clear All Filters */}
            {activeFiltersCount > 0 && (
                <div className="flex justify-between items-center">
                    <span className="text-sm font-medium">
                        Filters ({activeFiltersCount})
                    </span>
                    <Button variant="ghost" size="sm" onClick={onClearAll}>
                        Clear All
                    </Button>
                </div>
            )}

            {/* Categories */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                        <Tag className="h-4 w-4" />
                        Categories
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {categories.map((category) => (
                        <div key={category.id} className="flex items-center space-x-2">
                            <Checkbox
                                id={`category-${category.id}`}
                                checked={filters.categories.includes(category.id)}
                                onCheckedChange={() => onCategoryToggle(category.id)}
                            />
                            <Label
                                htmlFor={`category-${category.id}`}
                                className="text-sm flex-1 cursor-pointer"
                            >
                                {category.name}
                                <span className="text-muted-foreground ml-2">({category.count})</span>
                            </Label>
                        </div>
                    ))}
                </CardContent>
            </Card>

            {/* Price Range */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                        <DollarSign className="h-4 w-4" />
                        Price Range
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-3">
                        <Slider
                            value={[filters.priceMin, filters.priceMax]}
                            onValueChange={(value) => onPriceChange(value[0], value[1])}
                            max={1000}
                            min={0}
                            step={25}
                            className="w-full"
                        />
                        <div className="flex justify-between text-sm text-muted-foreground">
                            <span>£{filters.priceMin}</span>
                            <span>£{filters.priceMax}</span>
                        </div>
                    </div>
                    <div className="space-y-2">
                        {priceRanges.map((range, index) => (
                            <Button
                                key={index}
                                variant="ghost"
                                size="sm"
                                onClick={() => onPriceChange(range.min, range.max || 1000)}
                                className="w-full justify-start h-8 text-xs"
                            >
                                {range.label}
                            </Button>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Availability */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                        <Package className="h-4 w-4" />
                        Availability
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {availabilityOptions.map((option) => (
                        <div key={option.value} className="flex items-center space-x-2">
                            <Checkbox
                                id={`availability-${option.value}`}
                                checked={filters.availability.includes(option.value)}
                                onCheckedChange={() => onAvailabilityToggle(option.value)}
                            />
                            <Label
                                htmlFor={`availability-${option.value}`}
                                className="text-sm flex-1 cursor-pointer"
                            >
                                {option.label}
                                <span className="text-muted-foreground ml-2">({option.count})</span>
                            </Label>
                        </div>
                    ))}
                </CardContent>
            </Card>

            {/* Rating */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                        <Star className="h-4 w-4" />
                        Rating
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <RadioGroup
                        value={filters.rating?.toString() || ''}
                        onValueChange={(value) => onRatingChange(value ? parseInt(value) : null)}
                    >
                        {[5, 4, 3, 2, 1].map((rating) => (
                            <div key={rating} className="flex items-center space-x-2">
                                <RadioGroupItem value={rating.toString()} id={`rating-${rating}`} />
                                <Label
                                    htmlFor={`rating-${rating}`}
                                    className="flex items-center gap-1 cursor-pointer"
                                >
                                    <div className="flex">
                                        {[...Array(5)].map((_, i) => (
                                            <Star
                                                key={i}
                                                className={cn(
                                                    'h-4 w-4',
                                                    i < rating
                                                        ? 'fill-yellow-400 text-yellow-400'
                                                        : 'text-muted-foreground'
                                                )}
                                            />
                                        ))}
                                    </div>
                                    <span className="text-sm text-muted-foreground ml-1">& up</span>
                                </Label>
                            </div>
                        ))}
                    </RadioGroup>
                </CardContent>
            </Card>

            {/* Brands */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                        <Sparkles className="h-4 w-4" />
                        Brands
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {brands.map((brand) => (
                        <div key={brand.id} className="flex items-center space-x-2">
                            <Checkbox
                                id={`brand-${brand.id}`}
                                checked={filters.brands.includes(brand.id)}
                                onCheckedChange={() => onBrandToggle(brand.id)}
                            />
                            <Label
                                htmlFor={`brand-${brand.id}`}
                                className="text-sm flex-1 cursor-pointer"
                            >
                                {brand.name}
                                <span className="text-muted-foreground ml-2">({brand.count})</span>
                            </Label>
                        </div>
                    ))}
                </CardContent>
            </Card>

            {/* Attributes */}
            {attributes.map((attribute) => (
                <Card key={attribute.name}>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <Palette className="h-4 w-4" />
                            {attribute.name}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {attribute.values.map((value) => (
                            <div key={value.value} className="flex items-center space-x-2">
                                <Checkbox
                                    id={`${attribute.name}-${value.value}`}
                                    checked={(filters.attributes[attribute.name] || []).includes(value.value)}
                                    onCheckedChange={() => onAttributeToggle(attribute.name, value.value)}
                                />
                                <Label
                                    htmlFor={`${attribute.name}-${value.value}`}
                                    className="text-sm flex-1 cursor-pointer"
                                >
                                    {value.label}
                                    <span className="text-muted-foreground ml-2">({value.count})</span>
                                </Label>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
};