<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
  protected static ?string $password;

  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'email' => fake()->unique()->safeEmail(),
      'password' => static::$password ??= Hash::make('password'),
      'username' => fake()->userName(),
      'img_path' => fake()->image(null, 360, 360, 'animals', true),
      'role' => User::ROLE_CLIENT
    ];
  }


  public function owner(): Factory
  {
    return $this->state(function (array $attributes) {
      return [
        'role' => User::ROLE_OWNER,
      ];
    });
  }
}