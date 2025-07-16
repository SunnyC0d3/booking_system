<?php

namespace App\Models;

use App\Constants\ProductStatuses;
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
        'rating_breakdown'
    ];

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

    public function scopeFilter(Builder $builder, QueryFilter $filters)
    {
        return $filters->apply($builder);
    }

    public function addSecureMedia(UploadedFile $file, string $collection = 'default'): Media
    {
        $mediaService = app(SecureMedia::class);
        return $mediaService->addSecureMedia($this, $file, $collection);
    }

    public function getPriceFormattedAttribute(): string
    {
        return '£' . number_format($this->price / 100, 2);
    }

    public function isAvailable(): bool
    {
        return $this->productStatus && $this->productStatus->name === ProductStatuses::ACTIVE && $this->quantity > 0;
    }

    public function getFeaturedImageAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured_image');
        return $media ? $media->getUrl() : null;
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->quantity >= $quantity;
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

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold && $this->quantity > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

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
        ];
    }

    public function canUserReview(User $user): bool
    {
        $hasPurchased = OrderItem::whereHas('order', function($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereIn('status_id', [3, 6]);
        })->where('product_id', $this->id)->exists();

        if (!$hasPurchased) {
            return false;
        }

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

    public function requiresShipping(): bool
    {
        return $this->requires_shipping && !$this->is_virtual;
    }

    public function isVirtual(): bool
    {
        return $this->is_virtual;
    }

    public function getShippingClass(): string
    {
        return $this->shipping_class ?? 'standard';
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
            'shipping_class' => $this->getShippingClass(),
            'requires_shipping' => $this->requiresShipping(),
            'is_virtual' => $this->isVirtual(),
            'handling_time_days' => $this->getHandlingTimeDays(),
            'restrictions' => $this->getShippingRestrictions(),
        ];
    }

    public function isDangerous(): bool
    {
        $restrictions = $this->getShippingRestrictions();
        return isset($restrictions['dangerous_goods']) && $restrictions['dangerous_goods'] === true;
    }

    public function requiresSignature(): bool
    {
        $restrictions = $this->getShippingRestrictions();
        return isset($restrictions['signature_required']) && $restrictions['signature_required'] === true;
    }

    public function isFragile(): bool
    {
        $restrictions = $this->getShippingRestrictions();
        return isset($restrictions['fragile']) && $restrictions['fragile'] === true;
    }
}
