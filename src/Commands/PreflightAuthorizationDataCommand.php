<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PreflightAuthorizationDataCommand extends Command
{
    protected $signature = 'role-permission:preflight {--json : Emit machine-readable JSON}';

    protected $description = 'Read-only authorization data preflight for a package upgrade';

    public function handle(): int
    {
        $findings = array_merge(
            $this->permissionIdentityDuplicates(),
            $this->roleCrossFieldCollisions(),
            $this->hierarchyCycles(),
            $this->pivotDuplicates(),
        );

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['ok' => $findings === [], 'findings' => $findings],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));
        } elseif ($findings === []) {
            $this->info('Authorization data preflight passed.');
        } else {
            foreach ($findings as $finding) {
                $this->error(sprintf(
                    '[%s] %s (ids: %s)',
                    $finding['type'],
                    $finding['identity'],
                    implode(',', $finding['ids']),
                ));
            }
        }

        return $findings === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<array{type: string, identity: string, ids: list<int|string>}>
     */
    private function permissionIdentityDuplicates(): array
    {
        $table = (string) config('role-permission-manager.permissions_table');
        $groups = [];

        foreach (DB::table($table)->select(['id', 'name', 'slug', 'scope'])->orderBy('id')->cursor() as $row) {
            $scope = $row->scope === null ? 'null' : 'string:' . $row->scope;
            foreach (['name', 'slug'] as $field) {
                $identity = "{$field}:{$row->{$field}}|scope:{$scope}";
                $groups[$identity][] = $row->id;
            }
        }

        $findings = [];
        foreach ($groups as $identity => $ids) {
            if (count($ids) > 1) {
                $findings[] = [
                    'type' => 'permission-duplicate',
                    'identity' => $identity,
                    'ids' => $ids,
                ];
            }
        }

        return $findings;
    }

    /**
     * @return list<array{type: string, identity: string, ids: list<int|string>}>
     */
    private function roleCrossFieldCollisions(): array
    {
        $table = (string) config('role-permission-manager.roles_table');
        $roles = DB::table($table)->select(['id', 'name', 'slug'])->orderBy('id')->get();
        $findings = [];

        foreach ($roles as $left) {
            foreach ($roles as $right) {
                if ($left->id !== $right->id && $left->name === $right->slug) {
                    $findings[] = [
                        'type' => 'role-cross-field-collision',
                        'identity' => "name/slug:{$left->name}",
                        'ids' => [$left->id, $right->id],
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * @return list<array{type: string, identity: string, ids: list<int|string>}>
     */
    private function hierarchyCycles(): array
    {
        $table = (string) config('role-permission-manager.roles_table');
        $parents = DB::table($table)->pluck('parent_id', 'id')->all();
        $cycles = [];

        foreach (array_keys($parents) as $start) {
            $path = [];
            $current = $start;
            while ($current !== null && array_key_exists($current, $parents)) {
                if (isset($path[(string) $current])) {
                    $cycleIds = array_map(
                        static fn (string $id): int|string => ctype_digit($id) ? (int) $id : $id,
                        array_keys($path),
                    );
                    sort($cycleIds);
                    $cycles[implode(',', $cycleIds)] = $cycleIds;
                    break;
                }

                $path[(string) $current] = true;
                $current = $parents[$current];
            }
        }

        return array_values(array_map(
            static fn (array $ids): array => [
                'type' => 'role-hierarchy-cycle',
                'identity' => 'cycle',
                'ids' => $ids,
            ],
            $cycles,
        ));
    }

    /**
     * @return list<array{type: string, identity: string, ids: list<int|string>}>
     */
    private function pivotDuplicates(): array
    {
        $definitions = [
            [
                (string) config('role-permission-manager.role_permission_table'),
                ['role_id', 'permission_id'],
            ],
            [
                (string) config('role-permission-manager.user_role_table'),
                [config('role-permission-manager.user_foreign_key', 'user_id'), 'role_id'],
            ],
            [
                (string) config('role-permission-manager.user_permission_table'),
                [config('role-permission-manager.user_foreign_key', 'user_id'), 'permission_id'],
            ],
        ];
        $findings = [];

        foreach ($definitions as [$table, $columns]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->select($columns)
                ->selectRaw('COUNT(*) AS aggregate')
                ->groupBy($columns)
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($rows as $row) {
                $identity = implode('|', array_map(
                    static fn (string $column): string => "{$column}:{$row->{$column}}",
                    $columns,
                ));
                $findings[] = [
                    'type' => 'pivot-duplicate',
                    'identity' => "{$table}|{$identity}",
                    'ids' => [],
                ];
            }
        }

        return $findings;
    }
}
