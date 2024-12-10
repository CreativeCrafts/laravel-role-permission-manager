<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Database\Factories;

use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestUserFactory extends Factory
{
    protected $model = TestUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}
