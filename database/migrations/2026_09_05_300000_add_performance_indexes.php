<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the hottest tenant-scoped filter combinations that the
 * per-table migrations did not already cover. Each is added only if absent so
 * the migration is safe to re-run.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, array<int, string>>>
     */
    private array $indexes = [
        'employees' => [
            ['tenant_id', 'department_id'],
            ['tenant_id', 'manager_id'],
        ],
        'attendances' => [
            ['tenant_id', 'work_date'],
        ],
        'payslips' => [
            ['tenant_id', 'employee_id'],
        ],
        'notifications' => [
            ['notifiable_type', 'notifiable_id', 'read_at'],
        ],
        'goals' => [
            ['tenant_id', 'employee_id', 'performance_cycle_id'],
        ],
        'audit_logs' => [
            ['tenant_id', 'actor_id', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $index) {
                    $name = $table.'_'.implode('_', $index).'_index';

                    if (! Schema::hasIndex($table, $name)) {
                        $blueprint->index($index, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $index) {
                    $name = $table.'_'.implode('_', $index).'_index';

                    if (Schema::hasIndex($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }
};
