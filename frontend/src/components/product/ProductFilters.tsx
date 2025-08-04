'use client'

import * as React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    ChevronDown,
    ChevronUp,
    SlidersHorizontal,
    Package,
    Tag,
    Star,
    DollarSign,
    Palette,
    Filter,
    X,
    Check,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui';
import {
    ProductFilters as ProductFiltersType,
    ProductSearchParams,
    FilterCategory,
    FilterAttribute,
    FilterAttributeValue,
    FilterTag,
    FilterAvailability,
    FilterRating,
    AttributeType,
} from '@/types/product';
import { cn } from '@/lib/cn';

export interface ProductFiltersProps {
    filters?: ProductFiltersType;
    selectedFilters?: ProductSearchParams;
    onFilterChange?: (filters: ProductSearchParams) => void;
    onClearFilters?: () => void;
    loading?: boolean;
    className?: string;
}

export const ProductFilters: React.FC<ProductFiltersProps> = ({
                                                                  filters,
                                                                  selectedFilters = {},
                                                                  onFilterChange,
                                                                  onClearFilters,
                                                                  loading = false,
                                                                  className,
                                                              }) => {
    const [openSections, setOpenSections] = React.useState<string[]>([
        'categories',
        'price',
    ]);
    const [priceRange, setPriceRange] = React.useState({
        min: selectedFilters.price_min?.toString() || '',
        max: selectedFilters.price_max?.toString() || '',
    });

    const toggleSection = (section: string) => {
        setOpenSections((prev) =>
            prev.includes(section)
                ? prev.filter((s) => s !== section)
                : [...prev, section]
        );
    };

    const handleCategoryChange = (categoryId: number, checked: boolean) => {
        const currentCategories = Array.isArray(selectedFilters.category)
            ? selectedFilters.category
            : selectedFilters.category
                ? [selectedFilters.category]
                : [];

        const newCategories = checked
            ? [...currentCategories, categoryId.toString()]
            : currentCategories.filter((id) => id !== categoryId.toString());

        onFilterChange?.({
            ...selectedFilters,
            category: newCategories.length > 0 ? newCategories : undefined,
        });
    };

    const handlePriceRangeChange = (type: 'min' | 'max', value: string) => {
        const newPriceRange = { ...priceRange, [type]: value };
        setPriceRange(newPriceRange);

        // Debounced update to avoid too many API calls
        const timeoutId = setTimeout(() => {
            onFilterChange?.({
                ...selectedFilters,
                price_min: newPriceRange.min
                    ? parseFloat(newPriceRange.min)
                    : undefined,
                price_max: newPriceRange.max
                    ? parseFloat(newPriceRange.max)
                    : undefined,
            });
        }, 500);

        return () => clearTimeout(timeoutId);
    };

    const handleAttributeChange = (
        attributeSlug: string,
        valueId: number,
        checked: boolean
    ) => {
        const currentAttributes = selectedFilters.attributes || {};
        const currentValues = Array.isArray(currentAttributes[attributeSlug])
            ? (currentAttributes[attributeSlug] as string[])
            : currentAttributes[attributeSlug]
                ? [currentAttributes[attributeSlug] as string]
                : [];

        const newValues = checked
            ? [...currentValues, valueId.toString()]
            : currentValues.filter((id) => id !== valueId.toString());

        const newAttributes = {
            ...currentAttributes,
            [attributeSlug]: newValues.length > 0 ? newValues : undefined,
        };

        // Clean up undefined values
        Object.keys(newAttributes).forEach((key) => {
            if (newAttributes[key] === undefined) {
                delete newAttributes[key];
            }
        });

        onFilterChange?.({
            ...selectedFilters,
            attributes: Object.keys(newAttributes).length > 0 ? newAttributes : undefined,
        });
    };

    const handleTagChange = (tagSlug: string, checked: boolean) => {
        const currentTags = Array.isArray(selectedFilters.tags)
            ? selectedFilters.tags
            : selectedFilters.tags
                ? [selectedFilters.tags]
                : [];

        const newTags = checked
            ? [...currentTags, tagSlug]
            : currentTags.filter((tag) => tag !== tagSlug);

        onFilterChange?.({
            ...selectedFilters,
            tags: newTags.length > 0 ? newTags : undefined,
        });
    };

    const handleAvailabilityChange = (key: string, checked: boolean) => {
        const currentAvailability = Array.isArray(selectedFilters.availability)
            ? selectedFilters.availability
            : selectedFilters.availability
                ? [selectedFilters.availability]
                : [];

        const newAvailability = checked
            ? [...currentAvailability, key]
            : currentAvailability.filter((item) => item !== key);

        onFilterChange?.({
            ...selectedFilters,
            availability: newAvailability.length > 0 ? newAvailability : undefined,
        });
    };

    const handleRatingChange = (rating: number) => {
        onFilterChange?.({
            ...selectedFilters,
            rating: selectedFilters.rating === rating ? undefined : rating,
        });
    };

    const hasActiveFilters = React.useMemo(() => {
        return Object.keys(selectedFilters).some((key) => {
            const value = selectedFilters[key as keyof ProductSearchParams];
            return (
                value !== undefined &&
                value !== null &&
                (Array.isArray(value) ? value.length > 0 : true)
            );
        });
    }, [selectedFilters]);

    const getActiveFilterCount = React.useMemo(() => {
        let count = 0;
        if (selectedFilters.category) count++;
        if (selectedFilters.price_min || selectedFilters.price_max) count++;
        if (selectedFilters.attributes) {
            count += Object.keys(selectedFilters.attributes).length;
        }
        if (selectedFilters.tags) count++;
        if (selectedFilters.availability) count++;
        if (selectedFilters.rating) count++;
        return count;
    }, [selectedFilters]);

    if (loading) {
        return <FiltersSkeleton className={className} />;
    }

    if (!filters) {
        return null;
    }

    return (
        <Card className={className}>
            <CardHeader className="pb-4">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-lg font-semibold flex items-center gap-2">
                        <SlidersHorizontal className="h-4 w-4" />
                        Filters
                        {getActiveFilterCount > 0 && (
                            <span className="bg-primary text-primary-foreground text-xs px-2 py-1 rounded-full">
                                {getActiveFilterCount}
                            </span>
                        )}
                    </CardTitle>
                    {hasActiveFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onClearFilters}
                            className="text-primary hover:text-primary/80"
                        >
                            <X className="h-3 w-3 mr-1" />
                            Clear All
                        </Button>
                    )}
                </div>
            </CardHeader>

            <CardContent className="space-y-6">
                {/* Categories */}
                {filters.categories && filters.categories.length > 0 && (
                    <FilterSection
                        title="Categories"
                        icon={<Package className="h-4 w-4" />}
                        isOpen={openSections.includes('categories')}
                        onToggle={() => toggleSection('categories')}
                    >
                        <div className="space-y-2">
                            {filters.categories.map((category) => (
                                <CategoryFilter
                                    key={category.id}
                                    category={category}
                                    isSelected={category.selected}
                                    onChange={(checked) =>
                                        handleCategoryChange(category.id, checked)
                                    }
                                />
                            ))}
                        </div>
                    </FilterSection>
                )}

                {/* Price Range */}
                {filters.price_range && (
                    <FilterSection
                        title="Price Range"
                        icon={<DollarSign className="h-4 w-4" />}
                        isOpen={openSections.includes('price')}
                        onToggle={() => toggleSection('price')}
                    >
                        <div className="space-y-3">
                            <div className="flex gap-2">
                                <Input
                                    type="number"
                                    placeholder="Min"
                                    value={priceRange.min}
                                    onChange={(e) =>
                                        handlePriceRangeChange('min', e.target.value)
                                    }
                                    className="text-sm"
                                />
                                <Input
                                    type="number"
                                    placeholder="Max"
                                    value={priceRange.max}
                                    onChange={(e) =>
                                        handlePriceRangeChange('max', e.target.value)
                                    }
                                    className="text-sm"
                                />
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Range: £{filters.price_range.min} - £
                                {filters.price_range.max}
                            </div>
                        </div>
                    </FilterSection>
                )}

                {/* Attributes */}
                {filters.attributes &&
                    filters.attributes.map((attribute) => (
                        <FilterSection
                            key={attribute.id}
                            title={attribute.name}
                            icon={
                                attribute.type === 'color' ? (
                                    <Palette className="h-4 w-4" />
                                ) : (
                                    <Tag className="h-4 w-4" />
                                )
                            }
                            isOpen={openSections.includes(`attr-${attribute.slug}`)}
                            onToggle={() => toggleSection(`attr-${attribute.slug}`)}
                        >
                            <div className="space-y-2">
                                {attribute.values.map((value) => (
                                    <AttributeValueFilter
                                        key={value.id}
                                        attribute={attribute}
                                        value={value}
                                        isSelected={value.selected}
                                        onChange={(checked) =>
                                            handleAttributeChange(
                                                attribute.slug,
                                                value.id,
                                                checked
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        </FilterSection>
                    ))}

                {/* Tags */}
                {filters.tags && filters.tags.length > 0 && (
                    <FilterSection
                        title="Tags"
                        icon={<Tag className="h-4 w-4" />}
                        isOpen={openSections.includes('tags')}
                        onToggle={() => toggleSection('tags')}
                    >
                        <div className="flex flex-wrap gap-2">
                            {filters.tags.map((tag) => (
                                <TagFilter
                                    key={tag.id}
                                    tag={tag}
                                    isSelected={tag.selected}
                                    onChange={(checked) =>
                                        handleTagChange(tag.slug, checked)
                                    }
                                />
                            ))}
                        </div>
                    </FilterSection>
                )}

                {/* Availability */}
                {filters.availability && filters.availability.length > 0 && (
                    <FilterSection
                        title="Availability"
                        icon={<Package className="h-4 w-4" />}
                        isOpen={openSections.includes('availability')}
                        onToggle={() => toggleSection('availability')}
                    >
                        <div className="space-y-2">
                            {filters.availability.map((availability) => (
                                <label
                                    key={availability.key}
                                    className="flex items-center space-x-2 cursor-pointer text-sm hover:text-foreground transition-colors"
                                >
                                    <input
                                        type="checkbox"
                                        checked={availability.selected}
                                        onChange={(e) =>
                                            handleAvailabilityChange(
                                                availability.key,
                                                e.target.checked
                                            )
                                        }
                                        className="rounded border-input text-primary focus:ring-primary focus:ring-offset-0"
                                    />
                                    <span className="flex-1">
                                        {availability.label} ({availability.count})
                                    </span>
                                </label>
                            ))}
                        </div>
                    </FilterSection>
                )}

                {/* Rating */}
                {filters.rating && filters.rating.length > 0 && (
                    <FilterSection
                        title="Customer Rating"
                        icon={<Star className="h-4 w-4" />}
                        isOpen={openSections.includes('rating')}
                        onToggle={() => toggleSection('rating')}
                    >
                        <div className="space-y-2">
                            {filters.rating.map((rating) => (
                                <button
                                    key={rating.rating}
                                    onClick={() => handleRatingChange(rating.rating)}
                                    className={cn(
                                        'flex items-center space-x-2 w-full text-left p-2 rounded-md transition-colors',
                                        rating.selected
                                            ? 'bg-primary/10 text-primary'
                                            : 'hover:bg-muted'
                                    )}
                                >
                                    <div className="flex items-center gap-1">
                                        {Array.from({ length: 5 }).map((_, i) => (
                                            <Star
                                                key={i}
                                                className={cn(
                                                    'w-3 h-3',
                                                    i < rating.rating
                                                        ? 'fill-yellow-400 text-yellow-400'
                                                        : 'text-muted-foreground'
                                                )}
                                            />
                                        ))}
                                        <span className="text-sm ml-1">
                                            & up ({rating.count})
                                        </span>
                                    </div>
                                    {rating.selected && (
                                        <Check className="h-3 w-3 ml-auto text-primary" />
                                    )}
                                </button>
                            ))}
                        </div>
                    </FilterSection>
                )}
            </CardContent>
        </Card>
    );
};

// Sub-components
interface FilterSectionProps {
    title: string;
    icon: React.ReactNode;
    isOpen: boolean;
    onToggle: () => void;
    children: React.ReactNode;
}

const FilterSection: React.FC<FilterSectionProps> = ({
                                                         title,
                                                         icon,
                                                         isOpen,
                                                         onToggle,
                                                         children,
                                                     }) => {
    return (
        <div>
            <button
                onClick={onToggle}
                className="flex items-center justify-between w-full text-left font-medium mb-3 hover:text-primary transition-colors"
            >
                <span className="flex items-center gap-2">
                    {icon}
                    {title}
                </span>
                {isOpen ? (
                    <ChevronUp className="h-4 w-4" />
                ) : (
                    <ChevronDown className="h-4 w-4" />
                )}
            </button>

            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="overflow-hidden"
                    >
                        <div className="ml-6">{children}</div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
};

interface CategoryFilterProps {
    category: FilterCategory;
    isSelected: boolean;
    onChange: (checked: boolean) => void;
}

const CategoryFilter: React.FC<CategoryFilterProps> = ({
                                                           category,
                                                           isSelected,
                                                           onChange,
                                                       }) => {
    return (
        <div>
            <label className="flex items-center space-x-2 cursor-pointer text-sm hover:text-foreground transition-colors">
                <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={(e) => onChange(e.target.checked)}
                    className="rounded border-input text-primary focus:ring-primary focus:ring-offset-0"
                />
                <span className="flex-1">
                    {category.name} ({category.count})
                </span>
            </label>

            {/* Nested categories */}
            {category.children && category.children.length > 0 && (
                <div className="ml-4 mt-2 space-y-1">
                    {category.children.map((child) => (
                        <CategoryFilter
                            key={child.id}
                            category={child}
                            isSelected={child.selected}
                            onChange={onChange}
                        />
                    ))}
                </div>
            )}
        </div>
    );
};

interface AttributeValueFilterProps {
    attribute: FilterAttribute;
    value: FilterAttributeValue;
    isSelected: boolean;
    onChange: (checked: boolean) => void;
}

const AttributeValueFilter: React.FC<AttributeValueFilterProps> = ({
                                                                       attribute,
                                                                       value,
                                                                       isSelected,
                                                                       onChange,
                                                                   }) => {
    return (
        <label className="flex items-center space-x-2 cursor-pointer text-sm hover:text-foreground transition-colors">
            <input
                type="checkbox"
                checked={isSelected}
                onChange={(e) => onChange(e.target.checked)}
                className="rounded border-input text-primary focus:ring-primary focus:ring-offset-0"
            />
            <span className="flex items-center gap-2 flex-1">
                {/* Color swatch for color attributes */}
                {attribute.type === 'color' && value.color_code && (
                    <div
                        className="w-4 h-4 rounded-full border border-muted-foreground/20"
                        style={{ backgroundColor: value.color_code }}
                    />
                )}

                {/* Image preview for image attributes */}
                {attribute.type === 'image' && value.image && (
                    <img
                        src={value.image}
                        alt={value.value}
                        className="w-4 h-4 rounded object-cover"
                    />
                )}

                {value.value} ({value.count})
            </span>
        </label>
    );
};

interface TagFilterProps {
    tag: FilterTag;
    isSelected: boolean;
    onChange: (checked: boolean) => void;
}

const TagFilter: React.FC<TagFilterProps> = ({ tag, isSelected, onChange }) => {
    return (
        <button
            onClick={() => onChange(!isSelected)}
            className={cn(
                'px-2 py-1 text-xs rounded-full border transition-colors',
                isSelected
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'bg-background text-muted-foreground border-muted hover:border-muted-foreground'
            )}
        >
            {tag.name} ({tag.count})
        </button>
    );
};

// Loading skeleton
const FiltersSkeleton: React.FC<{ className?: string }> = ({ className }) => {
    return (
        <Card className={className}>
            <CardContent className="p-6 space-y-6">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="space-y-3">
                        <div className="h-4 bg-muted rounded w-24 animate-pulse" />
                        <div className="space-y-2 ml-6">
                            {Array.from({ length: 3 }).map((_, j) => (
                                <div key={j} className="h-3 bg-muted rounded animate-pulse" />
                            ))}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
};

// Mobile Filters Dialog
export interface MobileFiltersDialogProps extends ProductFiltersProps {
    children?: React.ReactNode;
}

export const MobileFiltersDialog: React.FC<MobileFiltersDialogProps> = ({
                                                                            children,
                                                                            ...filterProps
                                                                        }) => {
    return (
        <Dialog>
            <DialogTrigger asChild>
                {children || (
                    <Button variant="outline" size="sm">
                        <Filter className="h-4 w-4 mr-2" />
                        Filters
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-md max-h-[80vh] overflow-hidden flex flex-col">
                <DialogHeader>
                    <DialogTitle>Filter Products</DialogTitle>
                </DialogHeader>
                <div className="flex-1 overflow-y-auto">
                    <ProductFilters {...filterProps} className="border-0 shadow-none" />
                </div>
            </DialogContent>
        </Dialog>
    );
};

export default ProductFilters;