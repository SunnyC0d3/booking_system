'use client'

import * as React from 'react';
import { useRouter } from 'next/navigation';
import {
    Search,
    ChevronDown,
    ArrowUpDown
} from 'lucide-react';
import {
    Button,
    Input,
} from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import {
    SortOption,
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
    const { fetchProducts } = useProductStore(); // Use fetchProducts which actually exists in your store
    const router = useRouter();

    const handleSearch = async (searchQuery: string) => {
        if (!searchQuery.trim()) return;

        setIsLoading(true);
        try {
            // Use fetchProducts with search filter since that's what your store supports
            await fetchProducts({ search: searchQuery.trim() });
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
            >
                <div className="flex items-center gap-2">
                    <ArrowUpDown className="h-4 w-4" />
                    {currentSort?.label || 'Sort'}
                </div>
                <ChevronDown className="h-4 w-4" />
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

// Search-specific exports (renamed to avoid conflicts)
export const SearchBar = ProductSearchBar;
export const SearchSort = ProductSort;