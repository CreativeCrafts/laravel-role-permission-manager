<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Tests\Models;

final readonly class TestTeam
{
    public function __construct(public int $ownerId)
    {
    }
}
