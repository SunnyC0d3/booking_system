<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'order_item_id',
        'rating',
        'title',
        'content',
        'is_verified_purchase',
        'is_featured',
        'is_approved',
        'helpful_votes',
        'total_votes',
        'approved_at',
    ];

    protected $casts = [
        'is_verified_purchase' => 'boolean',
        'is_featured' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'rating' => 'integer',
        'helpful_votes' => 'integer',
        'total_votes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function helpfulnessVotes(): HasMany
    {
        return $this->hasMany(ReviewHelpfulness::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    public function response(): HasOne
    {
        return $this->hasOne(ReviewResponse::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReviewMedia::class)->orderBy('sort_order');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function scopeVerifiedPurchase(Builder $query): Builder
    {
        return $query->where('is_verified_purchase', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeByRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', $rating);
    }

    public function scopeWithRating(Builder $query, array $ratings): Builder
    {
        return $query->whereIn('rating', $ratings);
    }

    public function scopeHelpful(Builder $query, int $minVotes = 5): Builder
    {
        return $query->where('helpful_votes', '>=', $minVotes)
            ->where('total_votes', '>', 0)
            ->whereRaw('(helpful_votes / total_votes) >= 0.7');
    }

    public function getHelpfulnessRatio(): ?float
    {
        if ($this->total_votes === 0 || $this->total_votes === null) {
            return null;
        }

        return round(($this->helpful_votes / $this->total_votes) * 100, 1);
    }

    public function hasUserVoted(int $userId): bool
    {
        return $this->helpfulnessVotes()
            ->where('user_id', $userId)
            ->exists();
    }

    public function getUserVote(int $userId): ?bool
    {
        $vote = $this->helpfulnessVotes()
            ->where('user_id', $userId)
            ->first();

        return $vote ? $vote->is_helpful : null;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id &&
            $this->created_at->diffInDays(now()) <= 30;
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->user_id === $user->id ||
            $user->hasRole(['super admin', 'admin']);
    }

    public function markAsHelpful(User $user): bool
    {
        return $this->voteOnHelpfulness($user, true);
    }

    public function markAsNotHelpful(User $user): bool
    {
        return $this->voteOnHelpfulness($user, false);
    }

    private function voteOnHelpfulness(User $user, bool $isHelpful): bool
    {
        if ($this->user_id === $user->id) {
            return false;
        }

        $existingVote = $this->helpfulnessVotes()
            ->where('user_id', $user->id)
            ->first();

        if ($existingVote) {
            if ($existingVote->is_helpful !== $isHelpful) {
                $existingVote->update(['is_helpful' => $isHelpful]);
                $this->recalculateHelpfulness();
                return true;
            }
            return false;
        }

        $this->helpfulnessVotes()->create([
            'user_id' => $user->id,
            'is_helpful' => $isHelpful,
        ]);

        $this->recalculateHelpfulness();
        return true;
    }

    public function recalculateHelpfulness(): void
    {
        $votes = $this->helpfulnessVotes()->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN is_helpful = 1 THEN 1 ELSE 0 END) as helpful
        ')->first();

        $this->update([
            'total_votes' => $votes->total ?? 0,
            'helpful_votes' => $votes->helpful ?? 0,
        ]);
    }
}
