<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrivateMessage>
 */
class PrivateMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => $this->faker->randomNumber(),
            'page_id' => $this->faker->randomNumber(),
            'recipient_id' => $this->faker->randomNumber(),
            'message_id' => $this->faker->word,
            'message' => $this->faker->text(),
        ];
    }
}
