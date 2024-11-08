<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use Illuminate\Console\Command;

class LaravelRolePermissionManagerCommand extends Command
{
    public $signature = 'laravel-role-permission-manager';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
