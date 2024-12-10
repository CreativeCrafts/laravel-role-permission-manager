<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Database\Factories;

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word,
            'slug' => $this->faker->unique()->slug,
            'description' => $this->faker->sentence,
        ];
    }
}
