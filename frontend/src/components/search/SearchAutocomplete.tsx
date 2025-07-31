'use client'

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Search,
    Clock,
    TrendingUp,
    Package,
    Tag,
    Users,
    ArrowRight,
    X,
    History,
} from 'lucide-react';
import {
    Input,
    Button,
    Card,
    CardContent,
    Badge,
} from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import { cn } from '@/lib/cn';

// Search suggestion types
interface SearchSuggestion {
    id: string;
    type: 'product' | 'category' | 'brand' | 'query';
    title: string;
    subtitle?: string;
    image?: string;
    url: string;
    popularity?: number;
}

interface RecentSearch {
    id: string;
    query: string;
    timestamp: number;
    results_count?: number;
}

interface SearchAutocompleteProps {
    placeholder?: string;
    className?: string;
    onSearch?: (query: string) => void;
    showRecentSearches?: boolean;
    showSuggestions?: boolean;
    maxSuggestions?: number;
    debounceMs?: number;
}

export const SearchAutocomplete: React.FC<SearchAutocompleteProps> = ({
                                                                          placeholder = 'Search for products, categories, or keywords...',
                                                                          className,
                                                                          onSearch,
                                                                          showRecentSearches = true,
                                                                          showSuggestions = true,
                                                                          maxSuggestions = 8,
                                                                          debounceMs = 300,
                                                                      }) => {
    const router = useRouter();
    const { searchProducts } = useProductStore();

    // State
    const [query, setQuery] = React.useState('');
    const [suggestions, setSuggestions] = React.useState<SearchSuggestion[]>([]);
    const [recentSearches, setRecentSearches] = React.useState<RecentSearch[]>([]);
    const [isLoading, setIsLoading] = React.useState(false);
    const [isOpen, setIsOpen] = React.useState(false);
    const [selectedIndex, setSelectedIndex] = React.useState(-1);

    // Refs
    const inputRef = React.useRef<HTMLInputElement>(null);
    const containerRef = React.useRef<HTMLDivElement>(null);

    // Popular searches (mock data)
    const popularSearches = [
        'Wedding invitations',
        'Custom labels',
        'Gift tags',
        'Birthday cards',
        'Business stickers',
        'Thank you cards',
    ];

    // Load recent searches from localStorage
    React.useEffect(() => {
        const stored = localStorage.getItem('recentSearches');
        if (stored) {
            try {
                setRecentSearches(JSON.parse(stored));
            } catch (error) {
                console.error('Failed to parse recent searches:', error);
            }
        }
    }, []);

    // Debounced search function
    const debouncedSearch = React.useCallback(
        debounce(async (searchQuery: string) => {
            if (searchQuery.length < 2) {
                setSuggestions([]);
                setIsLoading(false);
                return;
            }

            try {
                setIsLoading(true);

                // Mock API call - replace with actual search suggestions API
                const mockSuggestions: SearchSuggestion[] = [
                    {
                        id: '1',
                        type: 'product',
                        title: 'Wedding Invitation Set',
                        subtitle: '£45.00',
                        image: '/images/products/wedding-invites.jpg',
                        url: '/products/wedding-invitation-set',
                    },
                    {
                        id: '2',
                        type: 'category',
                        title: 'Custom Labels',
                        subtitle: '245 products',
                        url: '/products?category=labels',
                    },
                    {
                        id: '3',
                        type: 'brand',
                        title: 'Premium Print Co.',
                        subtitle: '156 products',
                        url: '/products?brand=premium-print',
                    },
                    {
                        id: '4',
                        type: 'query',
                        title: searchQuery,
                        subtitle: 'Search for this',
                        url: `/search?q=${encodeURIComponent(searchQuery)}`,
                    },
                ];

                // Filter and limit suggestions
                const filtered = mockSuggestions
                    .filter(s => s.title.toLowerCase().includes(searchQuery.toLowerCase()))
                    .slice(0, maxSuggestions);

                setSuggestions(filtered);
            } catch (error) {
                console.error('Search suggestions failed:', error);
                setSuggestions([]);
            } finally {
                setIsLoading(false);
            }
        }, debounceMs),
        [maxSuggestions, debounceMs]
    );

    // Handle input change
    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setQuery(value);
        setSelectedIndex(-1);

        if (showSuggestions) {
            if (value.trim()) {
                setIsLoading(true);
                debouncedSearch(value);
            } else {
                setSuggestions([]);
                setIsLoading(false);
            }
        }
    };

    // Handle search submission
    const handleSearch = (searchQuery: string = query) => {
        if (!searchQuery.trim()) return;

        // Add to recent searches
        addToRecentSearches(searchQuery);

        // Close suggestions
        setIsOpen(false);
        setQuery('');

        // Execute search
        if (onSearch) {
            onSearch(searchQuery);
        } else {
            router.push(`/search?q=${encodeURIComponent(searchQuery)}`);
        }
    };

    // Add to recent searches
    const addToRecentSearches = (searchQuery: string) => {
        const newSearch: RecentSearch = {
            id: Date.now().toString(),
            query: searchQuery,
            timestamp: Date.now(),
        };

        const updated = [newSearch, ...recentSearches.filter(s => s.query !== searchQuery)]
            .slice(0, 10); // Keep only last 10 searches

        setRecentSearches(updated);
        localStorage.setItem('recentSearches', JSON.stringify(updated));
    };

    // Remove from recent searches
    const removeFromRecentSearches = (id: string) => {
        const updated = recentSearches.filter(s => s.id !== id);
        setRecentSearches(updated);
        localStorage.setItem('recentSearches', JSON.stringify(updated));
    };

    // Clear all recent searches
    const clearRecentSearches = () => {
        setRecentSearches([]);
        localStorage.removeItem('recentSearches');
    };

    // Handle keyboard navigation
    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        const totalItems = suggestions.length + (showRecentSearches ? recentSearches.length : 0);

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setSelectedIndex(prev => (prev < totalItems - 1 ? prev + 1 : -1));
                break;
            case 'ArrowUp':
                e.preventDefault();
                setSelectedIndex(prev => (prev > -1 ? prev - 1 : totalItems - 1));
                break;
            case 'Enter':
                e.preventDefault();
                if (selectedIndex >= 0) {
                    if (selectedIndex < suggestions.length) {
                        const suggestion = suggestions[selectedIndex];
                        router.push(suggestion.url);
                    } else {
                        const recentIndex = selectedIndex - suggestions.length;
                        const recent = recentSearches[recentIndex];
                        if (recent) {
                            handleSearch(recent.query);
                        }
                    }
                } else {
                    handleSearch();
                }
                break;
            case 'Escape':
                setIsOpen(false);
                inputRef.current?.blur();
                break;
        }
    };

    // Handle suggestion click
    const handleSuggestionClick = (suggestion: SearchSuggestion) => {
        if (suggestion.type === 'query') {
            handleSearch(suggestion.title);
        } else {
            router.push(suggestion.url);
        }
        setIsOpen(false);
    };

    // Handle recent search click
    const handleRecentSearchClick = (recent: RecentSearch) => {
        handleSearch(recent.query);
    };

    // Handle click outside to close
    React.useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const showDropdown = isOpen && (
        suggestions.length > 0 ||
        (showRecentSearches && recentSearches.length > 0) ||
        query.length < 2
    );

    return (
        <div ref={containerRef} className={cn('relative w-full', className)}>
            {/* Search Input */}
            <div className="relative">
                <Input
                    ref={inputRef}
                    type="text"
                    placeholder={placeholder}
                    value={query}
                    onChange={handleInputChange}
                    onKeyDown={handleKeyDown}
                    onFocus={() => setIsOpen(true)}
                    className="pr-12"
                />
                <Button
                    type="button"
                    size="sm"
                    onClick={() => handleSearch()}
                    className="absolute right-1 top-1 h-8 px-3"
                >
                    <Search className="h-4 w-4" />
                </Button>
            </div>

            {/* Dropdown */}
            <AnimatePresence>
                {showDropdown && (
                    <motion.div
                        initial={{ opacity: 0, y: -10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        transition={{ duration: 0.15 }}
                        className="absolute top-full left-0 right-0 z-50 mt-2"
                    >
                        <Card className="shadow-lg border">
                            <CardContent className="p-0 max-h-96 overflow-y-auto">
                                {/* Loading */}
                                {isLoading && (
                                    <div className="p-4 text-center">
                                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mx-auto"></div>
                                    </div>
                                )}

                                {/* Suggestions */}
                                {!isLoading && suggestions.length > 0 && (
                                    <div className="py-2">
                                        <div className="px-4 py-2 text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                            Suggestions
                                        </div>
                                        {suggestions.map((suggestion, index) => (
                                            <SuggestionItem
                                                key={suggestion.id}
                                                suggestion={suggestion}
                                                isSelected={selectedIndex === index}
                                                onClick={() => handleSuggestionClick(suggestion)}
                                            />
                                        ))}
                                    </div>
                                )}

                                {/* Recent Searches */}
                                {!isLoading && showRecentSearches && recentSearches.length > 0 && query.length < 2 && (
                                    <div className="py-2 border-t">
                                        <div className="px-4 py-2 flex items-center justify-between">
                                            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                                Recent Searches
                                            </span>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={clearRecentSearches}
                                                className="text-xs h-6 px-2"
                                            >
                                                Clear All
                                            </Button>
                                        </div>
                                        {recentSearches.slice(0, 5).map((recent, index) => (
                                            <RecentSearchItem
                                                key={recent.id}
                                                recent={recent}
                                                isSelected={selectedIndex === suggestions.length + index}
                                                onClick={() => handleRecentSearchClick(recent)}
                                                onRemove={() => removeFromRecentSearches(recent.id)}
                                            />
                                        ))}
                                    </div>
                                )}

                                {/* Popular Searches */}
                                {!isLoading && suggestions.length === 0 && query.length < 2 && (
                                    <div className="py-2 border-t">
                                        <div className="px-4 py-2 text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                            Popular Searches
                                        </div>
                                        <div className="px-4 pb-4">
                                            <div className="flex flex-wrap gap-2">
                                                {popularSearches.map((search) => (
                                                    <Button
                                                        key={search}
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleSearch(search)}
                                                        className="text-xs h-7"
                                                    >
                                                        {search}
                                                    </Button>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* No Results */}
                                {!isLoading && suggestions.length === 0 && query.length >= 2 && (
                                    <div className="p-4 text-center text-muted-foreground">
                                        <Search className="h-8 w-8 mx-auto mb-2 opacity-50" />
                                        <p className="text-sm">No suggestions found</p>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleSearch()}
                                            className="mt-2 text-xs"
                                        >
                                            Search for "{query}"
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
};

// Suggestion Item Component
interface SuggestionItemProps {
    suggestion: SearchSuggestion;
    isSelected: boolean;
    onClick: () => void;
}

const SuggestionItem: React.FC<SuggestionItemProps> = ({
                                                           suggestion,
                                                           isSelected,
                                                           onClick,
                                                       }) => {
    const getIcon = () => {
        switch (suggestion.type) {
            case 'product':
                return Package;
            case 'category':
                return Tag;
            case 'brand':
                return Users;
            case 'query':
                return Search;
            default:
                return Search;
        }
    };

    const Icon = getIcon();

    return (
        <button
            onClick={onClick}
            className={cn(
                'w-full px-4 py-3 flex items-center gap-3 hover:bg-muted/50 transition-colors text-left',
                isSelected && 'bg-muted'
            )}
        >
            {/* Icon or Image */}
            <div className="flex-shrink-0">
                {suggestion.image ? (
                    <img
                        src={suggestion.image}
                        alt={suggestion.title}
                        className="w-8 h-8 rounded object-cover"
                    />
                ) : (
                    <div className="w-8 h-8 bg-muted rounded flex items-center justify-center">
                        <Icon className="h-4 w-4 text-muted-foreground" />
                    </div>
                )}
            </div>

            {/* Content */}
            <div className="flex-1 min-w-0">
                <div className="font-medium text-foreground truncate">
                    {suggestion.title}
                </div>
                {suggestion.subtitle && (
                    <div className="text-sm text-muted-foreground truncate">
                        {suggestion.subtitle}
                    </div>
                )}
            </div>

            {/* Type Badge */}
            <div className="flex-shrink-0">
                <Badge variant="secondary" className="text-xs">
                    {suggestion.type}
                </Badge>
            </div>
        </button>
    );
};

// Recent Search Item Component
interface RecentSearchItemProps {
    recent: RecentSearch;
    isSelected: boolean;
    onClick: () => void;
    onRemove: () => void;
}

const RecentSearchItem: React.FC<RecentSearchItemProps> = ({
                                                               recent,
                                                               isSelected,
                                                               onClick,
                                                               onRemove,
                                                           }) => {
    const formatTime = (timestamp: number) => {
        const now = Date.now();
        const diff = now - timestamp;
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        if (hours < 1) return 'Just now';
        if (hours < 24) return `${hours}h ago`;
        return `${days}d ago`;
    };

    const handleRemove = (e: React.MouseEvent) => {
        e.stopPropagation();
        onRemove();
    };

    return (
        <button
            onClick={onClick}
            className={cn(
                'w-full px-4 py-3 flex items-center gap-3 hover:bg-muted/50 transition-colors text-left group',
                isSelected && 'bg-muted'
            )}
        >
            {/* Clock Icon */}
            <div className="flex-shrink-0">
                <div className="w-8 h-8 bg-muted rounded flex items-center justify-center">
                    <Clock className="h-4 w-4 text-muted-foreground" />
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 min-w-0">
                <div className="font-medium text-foreground truncate">
                    {recent.query}
                </div>
                <div className="text-sm text-muted-foreground">
                    {formatTime(recent.timestamp)}
                    {recent.results_count && (
                        <span className="ml-2">• {recent.results_count} results</span>
                    )}
                </div>
            </div>

            {/* Remove Button */}
            <div className="flex-shrink-0">
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={handleRemove}
                    className="w-6 h-6 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    <X className="h-3 w-3" />
                </Button>
            </div>
        </button>
    );
};

// Debounce utility function
function debounce<T extends (...args: any[]) => any>(
    func: T,
    wait: number
): (...args: Parameters<T>) => void {
    let timeout: NodeJS.Timeout;
    return (...args: Parameters<T>) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

export default SearchAutocomplete;