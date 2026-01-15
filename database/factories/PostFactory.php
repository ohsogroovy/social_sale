<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facebook_id' => $this->faker->randomNumber(),
            'user_id' => User::factory(),
            'message' => $this->faker->text(),
            'is_live' => $this->faker->boolean(),
            'post_type' => $this->faker->word(),
        ];
    }
}
