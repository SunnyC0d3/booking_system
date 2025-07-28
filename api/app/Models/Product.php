<?php

namespace App\Models;

use App\Constants\ProductStatuses;
use App\Constants\ShippingClasses;
use App\Constants\FulfillmentStatuses;
use App\Services\V1\Media\SecureMedia;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Filters\V1\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'product_category_id',
        'name',
        'description',
        'price',
        'quantity',
        'product_status_id',
        'low_stock_threshold',
        'total_reviews',
        'average_rating',
        'rating_breakdown',
        'weight',
        'length',
        'width',
        'height',
        'shipping_class',
        'requires_shipping',
        'is_virtual',
        'handling_time_days',
        'shipping_restrictions',
        'product_type',
        'requires_license',
        'auto_deliver',
        'download_limit',
        'download_expiry_days',
        'supported_platforms',
        'system_requirements',
        'latest_version',
        'version_control_enabled',
    ];

    protected $casts = [
        'rating_breakdown' => 'array',
        'shipping_restrictions' => 'array',
        'requires_shipping' => 'boolean',
        'is_virtual' => 'boolean',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'handling_time_days' => 'integer',
        'low_stock_threshold' => 'integer',
        'requires_license' => 'boolean',
        'auto_deliver' => 'boolean',
        'version_control_enabled' => 'boolean',
        'supported_platforms' => 'array',
        'system_requirements' => 'array',
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function productStatus(): BelongsTo
    {
        return $this->belongsTo(ProductStatus::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_tag', 'product_id', 'product_tag_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->approved();
    }

    public function featuredReviews(): HasMany
    {
        return $this->hasMany(Review::class)->featured();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Scopes
    public function scopeFilter(Builder $builder, QueryFilter $filters)
    {
        return $filters->apply($builder);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('productStatus', function ($q) {
            $q->where('name', ProductStatuses::ACTIVE);
        });
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->inStock();
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw('quantity <= low_stock_threshold AND quantity > 0');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeRequiresShipping(Builder $query): Builder
    {
        return $query->where('requires_shipping', true)
            ->where('is_virtual', false);
    }

    public function scopeVirtual(Builder $query): Builder
    {
        return $query->where('is_virtual', true);
    }

    public function scopeByShippingClass(Builder $query, string $shippingClass): Builder
    {
        return $query->where('shipping_class', $shippingClass);
    }

    public function scopeWithReviews(Builder $query): Builder
    {
        return $query->where('total_reviews', '>', 0);
    }

    public function scopeHighlyRated(Builder $query, float $minRating = 4.0): Builder
    {
        return $query->where('average_rating', '>=', $minRating);
    }

    // Media handling
    public function addSecureMedia(UploadedFile $file, string $collection = 'default'): Media
    {
        $mediaService = app(SecureMedia::class);
        return $mediaService->addSecureMedia($this, $file, $collection);
    }

    public function getFeaturedImageAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured_image');
        return $media ? $media->getUrl() : null;
    }

    public function getGalleryImages(): array
    {
        return $this->getMedia('gallery')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb'),
                'alt' => $media->getCustomProperty('alt', $this->name),
                'sort_order' => $media->getCustomProperty('sort_order', 0),
            ];
        })->sortBy('sort_order')->values()->toArray();
    }

    // Price methods
    public function getPriceFormattedAttribute(): string
    {
        return '£' . number_format($this->price / 100, 2);
    }

    public function getPriceInPennies(): int
    {
        return (int) $this->price;
    }

    public function getPriceInPounds(): float
    {
        return $this->price / 100;
    }

    public function setPriceFromPounds(float $pounds): void
    {
        $this->price = (int) round($pounds * 100);
    }

    public function setPriceFromPennies(int $pennies): void
    {
        $this->price = $pennies;
    }

    public function getEffectivePrice(?int $variantId = null): int
    {
        $price = $this->price;

        if ($variantId) {
            $variant = $this->variants()->find($variantId);
            if ($variant && $variant->additional_price) {
                $price += $variant->additional_price;
            }
        }

        return $price;
    }

    public function getEffectivePriceFormatted(?int $variantId = null): string
    {
        $price = $this->getEffectivePrice($variantId);
        return '£' . number_format($price / 100, 2);
    }

    // Availability methods
    public function isAvailable(): bool
    {
        return $this->productStatus &&
            $this->productStatus->name === ProductStatuses::ACTIVE &&
            $this->quantity > 0;
    }

    public function isActive(): bool
    {
        return $this->productStatus &&
            $this->productStatus->name === ProductStatuses::ACTIVE;
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->quantity >= $quantity;
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold && $this->quantity > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    public function isDiscontinued(): bool
    {
        return $this->productStatus &&
            $this->productStatus->name === ProductStatuses::DISCONTINUED;
    }

    public function isComingSoon(): bool
    {
        return $this->productStatus &&
            $this->productStatus->name === ProductStatuses::COMING_SOON;
    }

    public function getStockStatus(): string
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        }

        if ($this->isLowStock()) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function getStockStatusLabel(): string
    {
        return match($this->getStockStatus()) {
            'out_of_stock' => 'Out of Stock',
            'low_stock' => 'Low Stock',
            'in_stock' => 'In Stock',
            default => 'Unknown',
        };
    }

    public function getAvailabilityStatus(): string
    {
        if (!$this->isActive()) {
            return $this->productStatus->name;
        }

        return $this->getStockStatus();
    }

    // Review methods
    public function getAverageRatingAttribute(): float
    {
        return (float) ($this->attributes['average_rating'] ?? 0);
    }

    public function getTotalReviewsAttribute(): int
    {
        return (int) ($this->attributes['total_reviews'] ?? 0);
    }

    public function getRatingBreakdownAttribute(): array
    {
        $breakdown = $this->attributes['rating_breakdown'] ?? '{}';
        $decoded = json_decode($breakdown, true) ?? [];

        return array_merge([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $decoded);
    }

    public function hasReviews(): bool
    {
        return $this->total_reviews > 0;
    }

    public function getReviewsSummary(): array
    {
        return [
            'average_rating' => $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'rating_breakdown' => $this->rating_breakdown,
            'verified_purchase_count' => $this->reviews()->verifiedPurchase()->count(),
            'featured_reviews_count' => $this->reviews()->featured()->count(),
            'recent_reviews_count' => $this->reviews()->approved()->where('created_at', '>', now()->subDays(30))->count(),
        ];
    }

    public function canUserReview(User $user): bool
    {
        // Check if user has purchased this product
        $hasPurchased = OrderItem::whereHas('order', function($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereIn('status_id', [3, 6]); // Confirmed or Delivered orders
        })->where('product_id', $this->id)->exists();

        if (!$hasPurchased) {
            return false;
        }

        // Check if user hasn't already reviewed this product
        return !$this->reviews()->where('user_id', $user->id)->exists();
    }

    public function getUserReview(User $user): ?Review
    {
        return $this->reviews()->where('user_id', $user->id)->first();
    }

    public function recalculateReviewStats(): void
    {
        $reviews = $this->reviews()->approved();

        $stats = $reviews->selectRaw('
            COUNT(*) as total,
            AVG(rating) as average,
            COUNT(CASE WHEN rating = 1 THEN 1 END) as rating_1,
            COUNT(CASE WHEN rating = 2 THEN 1 END) as rating_2,
            COUNT(CASE WHEN rating = 3 THEN 1 END) as rating_3,
            COUNT(CASE WHEN rating = 4 THEN 1 END) as rating_4,
            COUNT(CASE WHEN rating = 5 THEN 1 END) as rating_5
        ')->first();

        $this->update([
            'total_reviews' => $stats->total ?? 0,
            'average_rating' => $stats->average ? round($stats->average, 2) : 0,
            'rating_breakdown' => json_encode([
                1 => $stats->rating_1 ?? 0,
                2 => $stats->rating_2 ?? 0,
                3 => $stats->rating_3 ?? 0,
                4 => $stats->rating_4 ?? 0,
                5 => $stats->rating_5 ?? 0,
            ]),
            'last_reviewed_at' => $this->reviews()->approved()->latest()->value('created_at'),
        ]);
    }

    // Weight and dimensions methods
    public function getWeightInGrams(): int
    {
        return (int) ($this->weight * 1000);
    }

    public function getWeightInKg(): float
    {
        return (float) $this->weight;
    }

    public function getWeightFormatted(): string
    {
        if ($this->weight >= 1) {
            return number_format($this->weight, 2) . 'kg';
        }

        return number_format($this->weight * 1000, 0) . 'g';
    }

    public function getDimensions(): array
    {
        return [
            'length' => (float) $this->length,
            'width' => (float) $this->width,
            'height' => (float) $this->height,
        ];
    }

    public function getDimensionsFormatted(): string
    {
        $dimensions = $this->getDimensions();
        return $dimensions['length'] . ' x ' . $dimensions['width'] . ' x ' . $dimensions['height'] . ' cm';
    }

    public function getVolumeInCubicCm(): float
    {
        $dimensions = $this->getDimensions();
        return $dimensions['length'] * $dimensions['width'] * $dimensions['height'];
    }

    public function getVolumeInLiters(): float
    {
        return $this->getVolumeInCubicCm() / 1000;
    }

    // Shipping methods
    public function requiresShipping(): bool
    {
        return $this->requires_shipping &&
            !$this->is_virtual &&
            in_array($this->product_type, ['physical', 'hybrid']);
    }

    public function isVirtual(): bool
    {
        return $this->is_virtual;
    }

    public function getShippingClass(): string
    {
        return $this->shipping_class ?? ShippingClasses::STANDARD;
    }

    public function getShippingClassLabel(): string
    {
        return ShippingClasses::getClassLabel($this->getShippingClass());
    }

    public function getHandlingTimeDays(): int
    {
        return $this->handling_time_days ?? 1;
    }

    public function getEstimatedShippingDate(): \Carbon\Carbon
    {
        return now()->addDays($this->getHandlingTimeDays());
    }

    public function hasShippingRestrictions(): bool
    {
        return !empty($this->shipping_restrictions);
    }

    public function getShippingRestrictions(): array
    {
        return $this->shipping_restrictions ?? [];
    }

    public function isRestrictedToCountry(string $countryCode): bool
    {
        $restrictions = $this->getShippingRestrictions();

        if (empty($restrictions)) {
            return false;
        }

        if (isset($restrictions['excluded_countries'])) {
            return in_array($countryCode, $restrictions['excluded_countries']);
        }

        if (isset($restrictions['allowed_countries'])) {
            return !in_array($countryCode, $restrictions['allowed_countries']);
        }

        return false;
    }

    public function canShipTo(string $countryCode): bool
    {
        if (!$this->requiresShipping()) {
            return true;
        }

        return !$this->isRestrictedToCountry($countryCode);
    }

    public function getShippingData(): array
    {
        return [
            'weight' => $this->getWeightInKg(),
            'weight_unit' => 'kg',
            'dimensions' => $this->getDimensions(),
            'dimension_unit' => 'cm',
            'volume' => $this->getVolumeInCubicCm(),
            'volume_unit' => 'cm³',
            'shipping_class' => $this->getShippingClass(),
            'shipping_class_label' => $this->getShippingClassLabel(),
            'requires_shipping' => $this->requiresShipping(),
            'is_virtual' => $this->isVirtual(),
            'handling_time_days' => $this->getHandlingTimeDays(),
            'estimated_shipping_date' => $this->getEstimatedShippingDate(),
            'restrictions' => $this->getShippingRestrictions(),
            'restricted_countries' => $this->getRestrictedCountries(),
        ];
    }

    public function getRestrictedCountries(): array
    {
        $restrictions = $this->getShippingRestrictions();

        if (isset($restrictions['excluded_countries'])) {
            return $restrictions['excluded_countries'];
        }

        return [];
    }

    public function getAllowedCountries(): array
    {
        $restrictions = $this->getShippingRestrictions();

        if (isset($restrictions['allowed_countries'])) {
            return $restrictions['allowed_countries'];
        }

        return []; // Empty array means all countries allowed
    }

    // Shipping class specific methods
    public function isDangerous(): bool
    {
        return $this->getShippingClass() === ShippingClasses::DANGEROUS ||
            (isset($this->getShippingRestrictions()['dangerous_goods']) &&
                $this->getShippingRestrictions()['dangerous_goods'] === true);
    }

    public function requiresSignature(): bool
    {
        $restrictions = $this->getShippingRestrictions();
        return isset($restrictions['signature_required']) && $restrictions['signature_required'] === true;
    }

    public function isFragile(): bool
    {
        return $this->getShippingClass() === ShippingClasses::FRAGILE ||
            (isset($this->getShippingRestrictions()['fragile']) &&
                $this->getShippingRestrictions()['fragile'] === true);
    }

    public function isHeavy(): bool
    {
        return $this->getShippingClass() === ShippingClasses::HEAVY;
    }

    public function isOversized(): bool
    {
        return $this->getShippingClass() === ShippingClasses::OVERSIZED;
    }

    public function requiresRefrigeration(): bool
    {
        return $this->getShippingClass() === ShippingClasses::REFRIGERATED;
    }

    public function isExpressShipping(): bool
    {
        return in_array($this->getShippingClass(), [
            ShippingClasses::EXPRESS,
            ShippingClasses::OVERNIGHT
        ]);
    }

    public function requiresSpecialHandling(): bool
    {
        return in_array($this->getShippingClass(), [
            ShippingClasses::FRAGILE,
            ShippingClasses::DANGEROUS,
            ShippingClasses::REFRIGERATED,
            ShippingClasses::OVERSIZED,
            ShippingClasses::HEAVY
        ]);
    }

    public function getShippingHandlingInstructions(): array
    {
        $instructions = [];

        if ($this->isFragile()) {
            $instructions[] = 'Handle with care - fragile item';
        }

        if ($this->isDangerous()) {
            $instructions[] = 'Dangerous goods - follow hazmat protocols';
        }

        if ($this->requiresRefrigeration()) {
            $instructions[] = 'Keep refrigerated during transport';
        }

        if ($this->isOversized()) {
            $instructions[] = 'Oversized package - may require special delivery';
        }

        if ($this->isHeavy()) {
            $instructions[] = 'Heavy item - use proper lifting techniques';
        }

        if ($this->requiresSignature()) {
            $instructions[] = 'Signature required upon delivery';
        }

        return $instructions;
    }

    // Inventory methods
    public function updateStock(int $quantity, string $reason = 'manual_adjustment'): void
    {
        $oldQuantity = $this->quantity;
        $this->quantity = max(0, $quantity);
        $this->save();

        // Log stock change
        \Log::info('Stock updated for product', [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $this->quantity,
            'change' => $this->quantity - $oldQuantity,
            'reason' => $reason,
        ]);
    }

    public function increaseStock(int $quantity, string $reason = 'stock_increase'): void
    {
        $this->updateStock($this->quantity + $quantity, $reason);
    }

    public function decreaseStock(int $quantity, string $reason = 'stock_decrease'): void
    {
        $this->updateStock($this->quantity - $quantity, $reason);
    }

    public function reserveStock(int $quantity): bool
    {
        if ($this->quantity >= $quantity) {
            $this->decreaseStock($quantity, 'stock_reserved');
            return true;
        }

        return false;
    }

    public function releaseStock(int $quantity): void
    {
        $this->increaseStock($quantity, 'stock_released');
    }

    public function getStockLevel(): string
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        }

        if ($this->isLowStock()) {
            return 'low_stock';
        }

        $threshold = $this->low_stock_threshold * 3;
        if ($this->quantity <= $threshold) {
            return 'medium_stock';
        }

        return 'high_stock';
    }

    public function getDaysUntilOutOfStock(): ?int
    {
        if ($this->isOutOfStock()) {
            return 0;
        }

        // Get average daily sales over last 30 days
        $dailySales = $this->orderItems()
                ->whereHas('order', function ($query) {
                    $query->where('created_at', '>', now()->subDays(30));
                })
                ->sum('quantity') / 30;

        if ($dailySales <= 0) {
            return null; // No sales data
        }

        return (int) ceil($this->quantity / $dailySales);
    }

    // Utility methods
    public function toArray(): array
    {
        $array = parent::toArray();

        // Add computed attributes
        $array['price_formatted'] = $this->getPriceFormattedAttribute();
        $array['weight_formatted'] = $this->getWeightFormatted();
        $array['dimensions_formatted'] = $this->getDimensionsFormatted();
        $array['is_available'] = $this->isAvailable();
        $array['stock_status'] = $this->getStockStatus();
        $array['stock_level'] = $this->getStockLevel();
        $array['shipping_class_label'] = $this->getShippingClassLabel();
        $array['requires_special_handling'] = $this->requiresSpecialHandling();
        $array['featured_image'] = $this->getFeaturedImageAttribute();

        return $array;
    }

    public function getSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'category' => $this->category?->name,
            'vendor' => $this->vendor?->name,
            'tags' => $this->tags->pluck('name')->toArray(),
            'status' => $this->productStatus?->name,
            'sku' => $this->sku ?? null,
            'shipping_class' => $this->getShippingClassLabel(),
        ];
    }

    public function getSlugAttribute(): string
    {
        return \Str::slug($this->name . '-' . $this->id);
    }

    public function getUrlAttribute(): string
    {
        return route('products.show', $this->getSlugAttribute());
    }

    public function productFiles(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function activeProductFiles(): HasMany
    {
        return $this->hasMany(ProductFile::class)->where('is_active', true);
    }

    public function primaryProductFile(): HasOne
    {
        return $this->hasOne(ProductFile::class)->where('is_primary', true);
    }

    public function downloadAccesses(): HasMany
    {
        return $this->hasMany(DownloadAccess::class);
    }

    public function licenseKeys(): HasMany
    {
        return $this->hasMany(LicenseKey::class);
    }

    public function productUpdates(): HasMany
    {
        return $this->hasMany(ProductUpdate::class);
    }

    public function latestUpdate(): HasOne
    {
        return $this->hasOne(ProductUpdate::class)->latest('released_at');
    }

// Add these scopes to the existing Product model
    public function scopeDigital(Builder $query): Builder
    {
        return $query->whereIn('product_type', ['digital', 'hybrid']);
    }

    public function scopePhysical(Builder $query): Builder
    {
        return $query->whereIn('product_type', ['physical', 'hybrid']);
    }

    public function scopeDigitalOnly(Builder $query): Builder
    {
        return $query->where('product_type', 'digital');
    }

    public function scopeWithLicense(Builder $query): Builder
    {
        return $query->where('requires_license', true);
    }

    public function scopeAutoDelivery(Builder $query): Builder
    {
        return $query->where('auto_deliver', true);
    }

// Add these methods to the existing Product model
    public function isDigital(): bool
    {
        return in_array($this->product_type, ['digital', 'hybrid']);
    }

    public function isPhysical(): bool
    {
        return in_array($this->product_type, ['physical', 'hybrid']);
    }

    public function isDigitalOnly(): bool
    {
        return $this->product_type === 'digital';
    }

    public function isHybrid(): bool
    {
        return $this->product_type === 'hybrid';
    }

    public function requiresLicense(): bool
    {
        return $this->requires_license;
    }

    public function hasAutoDelivery(): bool
    {
        return $this->auto_deliver;
    }

    public function hasVersionControl(): bool
    {
        return $this->version_control_enabled;
    }

    public function hasDigitalFiles(): bool
    {
        return $this->productFiles()->active()->exists();
    }

    public function getDigitalFilesCount(): int
    {
        return $this->productFiles()->active()->count();
    }

    public function getTotalDigitalFileSize(): int
    {
        return $this->productFiles()->active()->sum('file_size');
    }

    public function getTotalDigitalFileSizeFormatted(): string
    {
        $bytes = $this->getTotalDigitalFileSize();
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getSupportedPlatforms(): array
    {
        return $this->supported_platforms ?? [];
    }

    public function getSystemRequirements(): array
    {
        return $this->system_requirements ?? [];
    }

    public function supportsPlatform(string $platform): bool
    {
        $platforms = $this->getSupportedPlatforms();
        return empty($platforms) || in_array($platform, $platforms);
    }

    public function getLatestVersionAttribute(): ?string
    {
        return $this->latest_version ?? $this->productFiles()->active()->max('version') ?? '1.0.0';
    }

    public function createDownloadAccess(User $user, Order $order, ?ProductFile $file = null): DownloadAccess
    {
        return DownloadAccess::create([
            'user_id' => $user->id,
            'product_id' => $this->id,
            'order_id' => $order->id,
            'product_file_id' => $file?->id,
            'access_token' => DownloadAccess::generateToken(),
            'download_limit' => $this->download_limit,
            'expires_at' => now()->addDays($this->download_expiry_days),
        ]);
    }

    public function createLicenseKey(User $user, Order $order, string $type = 'single_use'): LicenseKey
    {
        return LicenseKey::create([
            'product_id' => $this->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'license_key' => LicenseKey::generateKey($this->getProductPrefix()),
            'type' => $type,
            'activation_limit' => $this->getActivationLimit($type),
            'expires_at' => $this->getLicenseExpiry($type),
        ]);
    }

    private function getProductPrefix(): string
    {
        return strtoupper(substr(preg_replace('/[^A-Z]/', '', $this->name), 0, 4)) ?: 'PROD';
    }

    private function getActivationLimit(string $type): int
    {
        return match($type) {
            'single_use' => 1,
            'multi_use' => 5,
            'subscription' => 10,
            'trial' => 1,
            default => 1
        };
    }

    private function getLicenseExpiry(string $type): ?\Carbon\Carbon
    {
        return match($type) {
            'trial' => now()->addDays(30),
            'subscription' => now()->addYear(),
            default => null
        };
    }

    public function getProductTypeLabel(): string
    {
        return match($this->product_type) {
            'physical' => 'Physical Product',
            'digital' => 'Digital Product',
            'hybrid' => 'Physical + Digital',
            default => 'Unknown'
        };
    }

    public function getProductTypeIcon(): string
    {
        return match($this->product_type) {
            'physical' => '📦',
            'digital' => '💾',
            'hybrid' => '📦💾',
            default => '❓'
        };
    }
}
