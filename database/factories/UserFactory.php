<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an active demo workspace.
     */
    public function demo(): static
    {
        return $this->state(function () {
            $demoId = 'TN-'.fake()->unique()->regexify('[A-HJ-KM-NP-Z2-9]{6}');

            return [
                'name' => 'Demo '.Str::after($demoId, 'TN-'),
                'email' => Str::lower($demoId).'@tasknet.local',
                'email_verified_at' => null,
                'demo_id' => $demoId,
                'is_demo' => true,
                'expires_at' => now()->addDays(config('tasknet.demo.lifetime_days')),
            ];
        });
    }

    /**
     * Indicate that the demo workspace has expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
