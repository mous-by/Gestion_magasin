<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Category;
use App\Models\Unite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'ART-'.fake()->unique()->numerify('#####'),
            'designation' => ucfirst(fake()->words(3, true)),
            'category_id' => Category::factory(),
            'unite_id' => Unite::factory(),
            'brand_model' => null,
            'min_threshold' => null,
            'location' => null,
            'observation' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
