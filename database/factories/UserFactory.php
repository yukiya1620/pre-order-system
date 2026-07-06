<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => User::ROLE_BUYER,
            'name' => fake()->name(),
            'phone_number' => '0'.fake()->unique()->numerify('##########'),
            'address' => fake()->address(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'notify_by_email' => false,
            'notify_by_sms' => false,
            'is_active' => true,
        ];
    }

    /**
     * 農家アカウントとして生成する
     */
    public function farmer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_FARMER,
        ]);
    }
}
