<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('has the composite performance indexes in place', function (string $table, string $index) {
    expect(Schema::hasIndex($table, $index))->toBeTrue();
})->with([
    ['employees', 'employees_tenant_id_department_id_index'],
    ['employees', 'employees_tenant_id_manager_id_index'],
    ['attendances', 'attendances_tenant_id_work_date_index'],
    ['payslips', 'payslips_tenant_id_employee_id_index'],
    ['notifications', 'notifications_notifiable_type_notifiable_id_read_at_index'],
    ['goals', 'goals_tenant_id_employee_id_performance_cycle_id_index'],
    ['audit_logs', 'audit_logs_tenant_id_actor_id_created_at_index'],
]);
