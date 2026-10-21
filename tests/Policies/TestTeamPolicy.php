<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Tests\Policies;

use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestTeam;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;

final class TestTeamPolicy
{
    public function update(TestUser $user, TestTeam $team): bool
    {
        return $user->getAuthIdentifier() === $team->ownerId;
    }

    public function view(TestUser $user, TestTeam $team): bool
    {
        return $user->getAuthIdentifier() === $team->ownerId;
    }
}
