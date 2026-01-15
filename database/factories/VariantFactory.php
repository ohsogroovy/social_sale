<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Variant>
 */
class VariantFactory extends Factory
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
            'product_id' => Product::factory(),
            'shopify_id' => $this->faker->randomNumber(8),
            'sku' => $this->faker->word(),
            'quantity' => $this->faker->randomNumber(4),
        ];
    }
}
