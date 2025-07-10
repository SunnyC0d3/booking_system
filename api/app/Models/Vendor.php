<?php

namespace App\Models;

use App\Filters\V1\QueryFilter;
use App\Services\V1\Media\SecureMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Vendor extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function reviewResponses(): HasMany
    {
        return $this->hasMany(ReviewResponse::class);
    }

    public function hasRespondedToReview(Review $review): bool
    {
        return $this->reviewResponses()
            ->where('review_id', $review->id)
            ->exists();
    }

    public function getUnansweredReviews()
    {
        return Review::whereHas('product', function($query) {
            $query->where('vendor_id', $this->id);
        })
            ->where('is_approved', true)
            ->whereDoesntHave('response')
            ->with(['user', 'product', 'media'])
            ->latest();
    }

    public function getResponseStats(): array
    {
        $totalReviews = Review::whereHas('product', function($query) {
            $query->where('vendor_id', $this->id);
        })->where('is_approved', true)->count();

        $totalResponses = $this->reviewResponses()->count();

        return [
            'total_reviews' => $totalReviews,
            'total_responses' => $totalResponses,
            'response_rate' => $totalReviews > 0 ? round(($totalResponses / $totalReviews) * 100, 1) : 0,
            'unanswered_count' => $totalReviews - $totalResponses,
        ];
    }

}
