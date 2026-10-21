<?php

declare(strict_types=1);

use Composer\InstalledVersions;

arch('package code uses strict types')
    ->expect('CreativeCrafts\LaravelRolePermissionManager')
    ->toUseStrictTypes();

arch('package code excludes debugging helpers')
    ->expect('CreativeCrafts\LaravelRolePermissionManager')
    ->not->toUse(['dd', 'dump', 'ray']);

if (version_compare(InstalledVersions::getVersion('pestphp/pest-plugin-arch') ?? '0', '3.0.0', '>=')) {
    arch()->preset()->laravel();
    arch()->preset()->php();
    arch()->preset()->security();
}
