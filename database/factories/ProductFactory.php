<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'handle' => $this->faker->slug,
            'image_url' => $this->faker->imageUrl(),
            'short_description' => $this->faker->sentence,
            'shopify_id' => $this->faker->randomNumber(8),
        ];
    }
}
