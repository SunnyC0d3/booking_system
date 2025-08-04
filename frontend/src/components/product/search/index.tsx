'use client'

import * as React from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import {
    Search,
    Filter,
    X,
    ChevronDown,
    SlidersHorizontal,
    Tag,
    Star,
    Package,
    ArrowUpDown
} from 'lucide-react';
import {
    Button,
    Input,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import {
    ProductSearchParams,
    ProductFilters,
    SortOption,
    FilterCategory,
    FilterAttribute
} from '@/types/product';
import { cn } from '@/lib/cn';

// Product Search Bar Component
interface ProductSearchBarProps {
    placeholder?: string;
    className?: string;
    onSearch?: (query: string) => void;
}

export const ProductSearchBar: React.FC<ProductSearchBarProps> = ({
                                                                      placeholder = "Search for labels, invitations, stickers...",
                                                                      className,
                                                                      onSearch,
                                                                  }) => {
    const [query, setQuery] = React.useState('');
    const [isLoading, setIsLoading] = React.useState(false);
    const { searchProducts } = useProductStore();
    const router = useRouter();

    const handleSearch = async (searchQuery: string) => {
        if (!searchQuery.trim()) return;

        setIsLoading(true);
        try {
            await searchProducts(searchQuery.trim());
            onSearch?.(searchQuery.trim());
            // Navigate to search results page
            router.push(`/products?q=${encodeURIComponent(searchQuery.trim())}`);
        } catch (error) {
            console.error('Search failed:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        handleSearch(query);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleSearch(query);
        }
    };

    return (
        <form onSubmit={handleSubmit} className={cn("relative", className)}>
            <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                <Input
                    type="text"
                    placeholder={placeholder}
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={handleKeyDown}
                    className="pl-10 pr-4 h-12 text-base"
                    disabled={isLoading}
                />
                {isLoading && (
                    <div className="absolute right-3 top-1/2 transform -translate-y-1/2">
                        <div className="loading-spinner w-4 h-4" />
                    </div>
                )}
            </div>
        </form>
    );
};

// Product Sort Component
interface ProductSortProps {
    options?: SortOption[];
    selected?: string;
    onSortChange?: (sort: string) => void;
    className?: string;
}

export const ProductSort: React.FC<ProductSortProps> = ({
                                                            options = [
                                                                { key: 'relevance', label: 'Most Relevant', direction: 'desc', selected: true },
                                                                { key: 'price-asc', label: 'Price: Low to High', direction: 'asc', selected: false },
                                                                { key: 'price-desc', label: 'Price: High to Low', direction: 'desc', selected: false },
                                                                { key: 'newest', label: 'Newest First', direction: 'desc', selected: false },
                                                                { key: 'rating', label: 'Highest Rated', direction: 'desc', selected: false },
                                                                { key: 'name-asc', label: 'Name: A to Z', direction: 'asc', selected: false },
                                                            ],
                                                            selected,
                                                            onSortChange,
                                                            className,
                                                        }) => {
    const [isOpen, setIsOpen] = React.useState(false);
    const currentSort = options.find(opt => opt.key === selected) || options[0];

    const handleSortChange = (sortKey: string) => {
        onSortChange?.(sortKey);
        setIsOpen(false);
    };

    return (
        <div className={cn("relative", className)}>
            <Button
                variant="outline"
                onClick={() => setIsOpen(!isOpen)}
                className="justify-between min-w-[160px]"
                rightIcon={<ChevronDown className="h-4 w-4" />}
            >
                <div className="flex items-center gap-2">
                    <ArrowUpDown className="h-4 w-4" />
                    {currentSort.label}
                </div>
            </Button>

            {isOpen && (
                <div className="absolute top-full left-0 mt-1 w-full min-w-[200px] bg-background border rounded-lg shadow-lg z-50">
                    {options.map((option) => (
                        <button
                            key={option.key}
                            onClick={() => handleSortChange(option.key)}
                            className={cn(
                                "w-full px-4 py-2 text-left text-sm hover:bg-muted transition-colors first:rounded-t-lg last:rounded-b-lg",
                                option.key === selected && "bg-primary/10 text-primary"
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
};

// Product Filters Component
interface ProductFiltersProps {
    filters?: ProductFilters;
    selectedFilters?: ProductSearchParams;
    onFilterChange?: (filters: ProductSearchParams) => void;
    onClearFilters?: () => void;
    className?: string;
}

export const ProductFilters: React.FC<ProductFiltersProps> = ({
                                                                  filters,
                                                                  selectedFilters = {},
                                                                  onFilterChange,
                                                                  onClearFilters,
                                                                  className,
                                                              }) => {
    const [openSections, setOpenSections] = React.useState<string[]>(['categories', 'price']);

    const toggleSection = (section: string) => {
        setOpenSections(prev =>
            prev.includes(section)
                ? prev.filter(s => s !== section)
                : [...prev, section]
        );
    };

    const handleCategoryChange = (categoryId: number, checked: boolean) => {
        const currentCategories = Array.isArray(selectedFilters.category)
            ? selectedFilters.category
            : selectedFilters.category ? [selectedFilters.category] : [];

        const newCategories = checked
            ? [...currentCategories, categoryId.toString()]
            : currentCategories.filter(id => id !== categoryId.toString());

        onFilterChange?.({
            ...selectedFilters,
            category: newCategories.length > 0 ? newCategories : undefined
        });
    };

    const handlePriceChange = (min?: number, max?: number) => {
        onFilterChange?.({
            ...selectedFilters,
            price_min: min,
            price_max: max,
        });
    };

    const handleAttributeChange = (attributeSlug: string, valueId: number, checked: boolean) => {
        const currentAttributes = selectedFilters.attributes || {};
        const currentValues = Array.isArray(currentAttributes[attributeSlug])
            ? currentAttributes[attributeSlug] as string[]
            : currentAttributes[attributeSlug] ? [currentAttributes[attributeSlug] as string] : [];

        const newValues = checked
            ? [...currentValues, valueId.toString()]
            : currentValues.filter(id => id !== valueId.toString());

        onFilterChange?.({
            ...selectedFilters,
            attributes: {
                ...currentAttributes,
                [attributeSlug]: newValues.length > 0 ? newValues : undefined
            }
        });
    };

    const hasActiveFilters = Object.keys(selectedFilters).some(key => {
        const value = selectedFilters[key as keyof ProductSearchParams];
        return value !== undefined && value !== null &&
            (Array.isArray(value) ? value.length > 0 : true);
    });

    if (!filters) {
        return (
            <Card className={className}>
                <CardContent className="p-6">
                    <div className="animate-pulse space-y-4">
                        <div className="h-4 bg-muted rounded w-24" />
                        <div className="space-y-2">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="h-3 bg-muted rounded" />
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={className}>
            <CardHeader className="pb-4">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-lg font-semibold flex items-center gap-2">
                        <SlidersHorizontal className="h-4 w-4" />
                        Filters
                    </CardTitle>
                    {hasActiveFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onClearFilters}
                            className="text-primary hover:text-primary/80"
                        >
                            Clear All
                        </Button>
                    )}
                </div>
            </CardHeader>

            <CardContent className="space-y-6">
                {/* Categories */}
                {filters.categories && filters.categories.length > 0 && (
                    <div>
                        <button
                            onClick={() => toggleSection('categories')}
                            className="flex items-center justify-between w-full text-left font-medium mb-3"
                        >
                            <span className="flex items-center gap-2">
                                <Package className="h-4 w-4" />
                                Categories
                            </span>
                            <ChevronDown className={cn(
                                "h-4 w-4 transition-transform",
                                openSections.includes('categories') && "rotate-180"
                            )} />
                        </button>

                        {openSections.includes('categories') && (
                            <div className="space-y-2 ml-6">
                                {filters.categories.map((category) => (
                                    <label
                                        key={category.id}
                                        className="flex items-center space-x-2 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={category.selected}
                                            onChange={(e) => handleCategoryChange(category.id, e.target.checked)}
                                            className="rounded border-gray-300 text-primary focus:ring-primary"
                                        />
                                        <span className="text-sm">
                                            {category.name} ({category.count})
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Price Range */}
                {filters.price_range && (
                    <div>
                        <button
                            onClick={() => toggleSection('price')}
                            className="flex items-center justify-between w-full text-left font-medium mb-3"
                        >
                            <span>Price Range</span>
                            <ChevronDown className={cn(
                                "h-4 w-4 transition-transform",
                                openSections.includes('price') && "rotate-180"
                            )} />
                        </button>

                        {openSections.includes('price') && (
                            <div className="space-y-3">
                                <div className="flex gap-2">
                                    <Input
                                        type="number"
                                        placeholder="Min"
                                        value={selectedFilters.price_min || ''}
                                        onChange={(e) => handlePriceChange(
                                            e.target.value ? Number(e.target.value) : undefined,
                                            selectedFilters.price_max
                                        )}
                                        className="text-sm"
                                    />
                                    <Input
                                        type="number"
                                        placeholder="Max"
                                        value={selectedFilters.price_max || ''}
                                        onChange={(e) => handlePriceChange(
                                            selectedFilters.price_min,
                                            e.target.value ? Number(e.target.value) : undefined
                                        )}
                                        className="text-sm"
                                    />
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Range: £{filters.price_range.min} - £{filters.price_range.max}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Attributes */}
                {filters.attributes && filters.attributes.map((attribute) => (
                    <div key={attribute.id}>
                        <button
                            onClick={() => toggleSection(`attr-${attribute.slug}`)}
                            className="flex items-center justify-between w-full text-left font-medium mb-3"
                        >
                            <span className="flex items-center gap-2">
                                <Tag className="h-4 w-4" />
                                {attribute.name}
                            </span>
                            <ChevronDown className={cn(
                                "h-4 w-4 transition-transform",
                                openSections.includes(`attr-${attribute.slug}`) && "rotate-180"
                            )} />
                        </button>

                        {openSections.includes(`attr-${attribute.slug}`) && (
                            <div className="space-y-2 ml-6">
                                {attribute.values.map((value) => (
                                    <label
                                        key={value.id}
                                        className="flex items-center space-x-2 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={value.selected}
                                            onChange={(e) => handleAttributeChange(
                                                attribute.slug,
                                                value.id,
                                                e.target.checked
                                            )}
                                            className="rounded border-gray-300 text-primary focus:ring-primary"
                                        />
                                        <span className="text-sm flex items-center gap-2">
                                            {value.color_code && (
                                                <div
                                                    className="w-3 h-3 rounded-full border"
                                                    style={{ backgroundColor: value.color_code }}
                                                />
                                            )}
                                            {value.value} ({value.count})
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>
                ))}

                {/* Rating Filter */}
                {filters.rating && filters.rating.length > 0 && (
                    <div>
                        <button
                            onClick={() => toggleSection('rating')}
                            className="flex items-center justify-between w-full text-left font-medium mb-3"
                        >
                            <span className="flex items-center gap-2">
                                <Star className="h-4 w-4" />
                                Rating
                            </span>
                            <ChevronDown className={cn(
                                "h-4 w-4 transition-transform",
                                openSections.includes('rating') && "rotate-180"
                            )} />
                        </button>

                        {openSections.includes('rating') && (
                            <div className="space-y-2 ml-6">
                                {filters.rating.map((rating) => (
                                    <label
                                        key={rating.rating}
                                        className="flex items-center space-x-2 cursor-pointer"
                                    >
                                        <input
                                            type="radio"
                                            name="rating"
                                            checked={rating.selected}
                                            onChange={() => onFilterChange?.({
                                                ...selectedFilters,
                                                rating: rating.rating
                                            })}
                                            className="text-primary focus:ring-primary"
                                        />
                                        <div className="flex items-center gap-1">
                                            {Array.from({ length: 5 }).map((_, i) => (
                                                <Star
                                                    key={i}
                                                    className={cn(
                                                        "w-3 h-3",
                                                        i < rating.rating
                                                            ? "fill-yellow-400 text-yellow-400"
                                                            : "text-muted-foreground"
                                                    )}
                                                />
                                            ))}
                                            <span className="text-sm ml-1">
                                                & up ({rating.count})
                                            </span>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

// Mobile Filters Dialog
interface MobileFiltersDialogProps {
    filters?: ProductFilters;
    selectedFilters?: ProductSearchParams;
    onFilterChange?: (filters: ProductSearchParams) => void;
    onClearFilters?: () => void;
}

export const MobileFiltersDialog: React.FC<MobileFiltersDialogProps> = (props) => {
    return (
        <Dialog>
            <DialogTrigger>
                <Button variant="outline" size="sm">
                    <Filter className="h-4 w-4 mr-2" />
                    Filters
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Filter Products</DialogTitle>
                </DialogHeader>
                <div className="max-h-[60vh] overflow-y-auto">
                    <ProductFilters {...props} className="border-0 shadow-none" />
                </div>
            </DialogContent>
        </Dialog>
    );
};