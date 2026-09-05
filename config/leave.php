<?php

return [

    // Ordered approval steps. Each is a role key resolved by LeaveService:
    //   manager -> the employee's line manager
    //   hr      -> any user holding the leave.approve permission
    // Tenants override via settings.leave.approval_chain.
    'approval_chain' => ['manager', 'hr'],

    // First month of the leave year (1-12).
    'year_start_month' => (int) env('LEAVE_YEAR_START_MONTH', 1),

    // ISO weekday numbers treated as non-working. Tenants override via
    // settings.leave.weekend_days.
    'weekend_days' => [6, 7],

];
