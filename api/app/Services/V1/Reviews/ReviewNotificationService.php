<?php

namespace App\Services\V1\Reviews;

use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\ReviewResponse;
use App\Models\User;
use App\Mail\ReviewApprovedMail;
use App\Mail\ReviewRejectedMail;
use App\Mail\NewReviewNotificationMail;
use App\Mail\ReviewResponseNotificationMail;
use App\Mail\ReviewReportedMail;
use App\Mail\ReviewFeaturedMail;
use App\Mail\ReviewHelpfulMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ReviewNotificationService
{
    public function sendReviewApproved(Review $review): void
    {
        try {
            $emailData = $this->prepareReviewEmailData($review);

            Mail::to($review->user->email)
                ->send(new ReviewApprovedMail($emailData));

            Log::info('Review approved notification sent', [
                'review_id' => $review->id,
                'user_id' => $review->user_id,
                'email' => $review->user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send review approved notification', [
                'review_id' => $review->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendReviewRejected(Review $review, string $reason = null): void
    {
        try {
            $emailData = $this->prepareReviewEmailData($review);
            $emailData['rejection_reason'] = $reason;

            Mail::to($review->user->email)
                ->send(new ReviewRejectedMail($emailData));

            Log::info('Review rejected notification sent', [
                'review_id' => $review->id,
                'user_id' => $review->user_id,
                'reason' => $reason
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send review rejected notification', [
                'review_id' => $review->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendNewReviewToVendor(Review $review): void
    {
        try {
            $vendor = $review->product->vendor;
            if (!$vendor || !$vendor->user) {
                return;
            }

            $emailData = $this->prepareReviewEmailData($review);

            Mail::to($vendor->user->email)
                ->send(new NewReviewNotificationMail($emailData));

            Log::info('New review notification sent to vendor', [
                'review_id' => $review->id,
                'vendor_id' => $vendor->id,
                'vendor_email' => $vendor->user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send new review notification to vendor', [
                'review_id' => $review->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendReviewResponse(ReviewResponse $response): void
    {
        try {
            $review = $response->review;
            $emailData = $this->prepareReviewEmailData($review);
            $emailData['response'] = [
                'id' => $response->id,
                'content' => $response->content,
                'vendor' => [
                    'id' => $response->vendor->id,
                    'name' => $response->vendor->name,
                ],
                'created_at' => $response->created_at->format('M j, Y')
            ];

            Mail::to($review->user->email)
                ->send(new ReviewResponseNotificationMail($emailData));

            Log::info('Review response notification sent', [
                'review_id' => $review->id,
                'response_id' => $response->id,
                'user_email' => $review->user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send review response notification', [
                'response_id' => $response->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendReviewReported(ReviewReport $report): void
    {
        try {
            $adminEmails = User::whereHas('role', function ($query) {
                $query->whereIn('name', ['super admin', 'admin']);
            })->pluck('email')->toArray();

            if (empty($adminEmails)) {
                Log::warning('No admin emails found for review report notification');
                return;
            }

            $emailData = $this->prepareReviewEmailData($report->review);
            $emailData['report'] = [
                'id' => $report->id,
                'reason' => $report->reason,
                'reason_label' => $report->reason_label,
                'details' => $report->details,
                'reported_by' => [
                    'name' => $report->reporter->name,
                    'email' => $report->reporter->email,
                ],
                'created_at' => $report->created_at->format('M j, Y g:i A')
            ];

            Mail::to($adminEmails)
                ->send(new ReviewReportedMail($emailData));

            Log::info('Review reported notification sent to admins', [
                'report_id' => $report->id,
                'review_id' => $report->review_id,
                'admin_count' => count($adminEmails)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send review reported notification', [
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendReviewFeatured(Review $review): void
    {
        try {
            $emailData = $this->prepareReviewEmailData($review);

            Mail::to($review->user->email)
                ->send(new ReviewFeaturedMail($emailData));

            Log::info('Review featured notification sent', [
                'review_id' => $review->id,
                'user_id' => $review->user_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send review featured notification', [
                'review_id' => $review->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendReviewHelpfulMilestone(Review $review): void
    {
        try {
            // Only send for certain milestones
            $milestones = [5, 10, 25, 50, 100];

            if (!in_array($review->helpful_votes, $milestones)) {
                return;
            }

            $emailData = $this->prepareReviewEmailData($review);

            Mail::to($review->user->email)
                ->send(new ReviewHelpfulMail($emailData));

            Log::info('Review helpful milestone notification sent', [
                'review_id' => $review->id,
                'helpful_votes' => $review->helpful_votes
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send review helpful milestone notification', [
                'review_id' => $review->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendWeeklyReviewDigest(User $vendorUser): void
    {
        try {
            if (!$vendorUser->hasRole('vendor')) {
                return;
            }

            $vendor = $vendorUser->vendors()->first();
            if (!$vendor) {
                return;
            }

            // Get reviews from last week
            $weekAgo = now()->subWeek();
            $reviews = Review::whereHas('product', function ($query) use ($vendor) {
                $query->where('vendor_id', $vendor->id);
            })
                ->where('created_at', '>=', $weekAgo)
                ->where('is_approved', true)
                ->with(['product', 'user'])
                ->get();

            if ($reviews->isEmpty()) {
                return;
            }

            $emailData = [
                'vendor' => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'user_name' => $vendorUser->name,
                ],
                'period' => [
                    'start' => $weekAgo->format('M j, Y'),
                    'end' => now()->format('M j, Y'),
                ],
                'reviews' => $reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'title' => $review->title,
                        'content' => substr($review->content, 0, 150) . '...',
                        'product_name' => $review->product->name,
                        'user_name' => $review->user->name,
                        'created_at' => $review->created_at->format('M j'),
                        'is_verified_purchase' => $review->is_verified_purchase,
                    ];
                })->toArray(),
                'stats' => [
                    'total_reviews' => $reviews->count(),
                    'average_rating' => round($reviews->avg('rating'), 1),
                    'verified_count' => $reviews->where('is_verified_purchase', true)->count(),
                    'response_needed' => $reviews->whereNull('response')->count(),
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send weekly review digest', [
                'vendor_user_id' => $vendorUser->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function prepareReviewEmailData(Review $review): array
    {
        return [
            'review' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'content' => $review->content,
                'is_verified_purchase' => $review->is_verified_purchase,
                'is_featured' => $review->is_featured,
                'helpful_votes' => $review->helpful_votes,
                'total_votes' => $review->total_votes,
                'created_at' => $review->created_at->format('M j, Y'),
                'product' => [
                    'id' => $review->product->id,
                    'name' => $review->product->name,
                    'price_formatted' => $review->product->price_formatted,
                    'featured_image' => $review->product->featured_image,
                ],
                'user' => [
                    'id' => $review->user->id,
                    'name' => $review->user->name,
                    'email' => $review->user->email,
                ]
            ],
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
            ]
        ];
    }
}
