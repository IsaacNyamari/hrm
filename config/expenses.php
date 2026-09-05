<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Approval chain
    |--------------------------------------------------------------------------
    | Ordered steps resolved by ExpenseService:
    |   manager -> the claimant's line manager
    |   finance -> any user holding the expenses.approve permission
    | Tenants override via settings.expenses.approval_chain.
    */
    'approval_chain' => ['manager', 'finance'],

    /*
    |--------------------------------------------------------------------------
    | Auto-approve threshold
    |--------------------------------------------------------------------------
    | Claims at or below this amount (minor units) skip the manager step.
    | null disables. Tenants override via settings.expenses.auto_approve_under_cents.
    */
    'auto_approve_under_cents' => null,

    /*
    |--------------------------------------------------------------------------
    | Mileage
    |--------------------------------------------------------------------------
    | Reimbursement rate per kilometre (minor units). Data, not code —
    | tenants override via settings.expenses.mileage_rate_cents_per_km.
    */
    'mileage_rate_cents_per_km' => (int) env('EXPENSE_MILEAGE_RATE_CENTS', 6000),

    /*
    |--------------------------------------------------------------------------
    | Receipt storage
    |--------------------------------------------------------------------------
    */
    'receipt_disk' => 'local',
    'receipt_max_kb' => 10240,

    // Categories that must carry a receipt on every line item.
    'receipt_required_categories' => ['travel', 'accommodation', 'training', 'medical', 'supplies'],

];
