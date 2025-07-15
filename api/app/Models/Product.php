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
}
