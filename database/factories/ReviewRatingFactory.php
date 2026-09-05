<?php

namespace Database\Factories;

use App\Models\PerformanceReview;
use App\Models\ReviewRating;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewRating>
 */
class ReviewRatingFactory extends Factory
{
    protected $model = ReviewRating::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'performance_review_id' => PerformanceReview::factory(),
            'competency' => fake()->unique()->randomElement([
                'job_knowledge', 'quality_of_work', 'communication', 'teamwork', 'initiative',
            ]),
            'weight' => 0,
            'rating' => fake()->numberBetween(2, 5),
        ];
    }
}
