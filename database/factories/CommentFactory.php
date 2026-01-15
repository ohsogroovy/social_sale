<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facebook_user_id' => $this->faker->randomNumber(),
            'commenter' => $this->faker->name(),
            'facebook_id' => $this->faker->randomNumber(),
            'post_id' => $this->faker->randomNumber(),
            'parent_id' => $this->faker->randomNumber(),
            'post_type' => $this->faker->word(),
            'message' => $this->faker->text(),
            'post_link' => $this->faker->url(),
            'facebook_created_at' => $this->faker->dateTime(),
            'is_from_page' => $this->faker->boolean(),
        ];
    }
}
