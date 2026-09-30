<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssetAssignmentController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceDeviceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardMetricsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DocumentExpiryController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeExportController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseItemController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HrRequestController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveCalendarController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\Me\DashboardController as MeDashboardController;
use App\Http\Controllers\Me\ProfileController as MeProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PaystackCallbackController;
use App\Http\Controllers\PerformanceCycleController;
use App\Http\Controllers\PerformanceReviewController;
use App\Http\Controllers\Platform\BillingController as PlatformBillingController;
use App\Http\Controllers\Platform\TenantController as PlatformTenantController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryComponentController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaxRuleController;
use App\Http\Controllers\UserInvitationController;
use App\Http\Controllers\VacancyController;
use App\Http\Controllers\WebhookEndpointController;
use App\Http\Controllers\WelcomeNewHireController;
use Illuminate\Support\Facades\Route;

/*
| Public marketing website.
*/
Route::view('/', 'marketing.home')->name('home');
Route::view('features', 'marketing.features')->name('features');
Route::get('pricing', PricingController::class)->name('pricing');
Route::view('about', 'marketing.about')->name('about');
Route::view('docs', 'marketing.docs')->name('docs');
Route::view('contact', 'marketing.contact')->name('contact');
Route::view('privacy', 'marketing.privacy')->name('privacy');
Route::view('request-a-quote', 'marketing.quote')->name('quote');

/*
| Public job board (all organizations) and no-account application tracking.
*/
Route::get('jobs', [JobsController::class, 'index'])->name('jobs.index');
Route::get('jobs/{organization}/{vacancy}', [JobsController::class, 'show'])->name('jobs.show');
Route::view('track-application', 'marketing.track')->name('applications.track');

/*
| First sign-in for a new hire: signed, single-use link from the welcome email.
*/
Route::middleware('throttle:20,1')->group(function () {
    Route::get('welcome/{user}', [WelcomeNewHireController::class, 'show'])->name('welcome.show');
    Route::post('welcome/{user}', [WelcomeNewHireController::class, 'store'])->name('welcome.store');
});

/*
| Invite-only registration. Public sign-up is closed (Fortify registration is
| disabled); a platform admin emails a single-use access link from a lead.
*/
Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('join/{token}', [JoinController::class, 'open'])->where('token', '[A-Za-z0-9]{64}')->name('join.open');
    Route::post('join/{token}', [JoinController::class, 'claim'])->where('token', '[A-Za-z0-9]{64}')->name('join.claim');
    Route::get('join', [JoinController::class, 'create'])->name('join.create');
    Route::post('join', [JoinController::class, 'store'])->name('join.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

/*
| Platform (SaaS operator) area. Sits OUTSIDE the tenant `ResolveTenant` group:
| no tenant is bound, so queries run cross-tenant. The `platform` middleware
| gates every page; the Livewire components behind them re-run that gate on
| every request (Livewire's update endpoint is not behind this group) and each
| mutating action re-checks its specific PlatformPermission.
*/
Route::middleware(['auth', 'verified', 'platform'])->prefix('platform')->name('platform.')->group(function () {
    Route::view('/', 'platform.dashboard')->name('dashboard');
    Route::view('tenants', 'platform.tenants')->name('tenants.index');
    Route::get('tenants/{tenant}', [PlatformTenantController::class, 'show'])->name('tenants.show');
    Route::view('plans', 'platform.plans')->name('plans.index');
    Route::view('billing', 'platform.billing')->name('billing');
    Route::get('billing/payments/{payment:uuid}', [PlatformBillingController::class, 'showPayment'])->name('billing.payments.show');
    Route::get('billing/invoices/{invoice:uuid}/pdf', [PlatformBillingController::class, 'downloadInvoice'])->name('billing.invoices.pdf');
    Route::view('leads', 'platform.leads')->name('leads');
    Route::view('settings', 'platform.settings')->name('settings');
    Route::view('deployments', 'platform.deployments')->name('deployments');
});

/*
| Platform operator impersonation of a tenant user. Guarded inside the
| controller (platform admin only); every start/stop is audited.
*/
Route::middleware(['auth'])->group(function () {
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
});

/*
| Invitation acceptance — guest, tenant is carried by the single-use token.
*/
Route::get('invitations/{invitation}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('invitations/{invitation}', [InvitationController::class, 'store'])->name('invitations.accept');

/*
| Tenant application. Every request is tenant-resolved, the user is verified to
| belong to that tenant, and the subscription is checked (billing.* and
| subscription.* stay reachable when it has lapsed). Queries are auto-scoped by
| App\Models\Scopes\TenantScope.
*/
Route::middleware(['auth', 'verified', 'tenant', 'tenant.user', 'subscription'])->group(function () {
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('billing/invoices/{invoice:uuid}/pdf', [BillingController::class, 'invoicePdf'])->name('billing.invoices.pdf');
    Route::get('billing/paystack/callback', PaystackCallbackController::class)->name('billing.paystack.callback');
    Route::post('subscription', [SubscriptionController::class, 'store'])->name('subscription.store');
    Route::delete('subscription', [SubscriptionController::class, 'destroy'])->name('subscription.destroy');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/export', EmployeeExportController::class)->name('employees.export');
    Route::post('employees/import/preview', [EmployeeImportController::class, 'preview'])->name('employees.import.preview');
    Route::post('employees/import/{import}/confirm', [EmployeeImportController::class, 'confirm'])->name('employees.import.confirm');
    Route::get('employees/import/{import}', [EmployeeImportController::class, 'show'])->name('employees.import.show');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::post('users/invitations', [UserInvitationController::class, 'store'])->name('users.invitations.store');

    // Organization structure
    Route::get('org-chart', OrgChartController::class)->name('org-chart');
    Route::get('departments/tree', [DepartmentController::class, 'tree'])->name('departments.tree');
    Route::apiResource('branches', BranchController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('designations', DesignationController::class);

    // Attendance — self-service clock actions need only a linked employee record.
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::post('attendance/break/start', [AttendanceController::class, 'startBreak'])->name('attendance.break.start');
    Route::post('attendance/break/end', [AttendanceController::class, 'endBreak'])->name('attendance.break.end');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');

    Route::post('employees/{employee}/shift', [ShiftController::class, 'assign'])->name('employees.shift.assign');
    Route::apiResource('shifts', ShiftController::class);
    Route::apiResource('attendance-devices', AttendanceDeviceController::class);

    // Leave
    Route::view('leave', 'leave.index')->name('leave');
    Route::apiResource('leave-types', LeaveTypeController::class);
    Route::apiResource('holidays', HolidayController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('leave-calendar', LeaveCalendarController::class)->name('leave-calendar');
    Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::get('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
    Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
    Route::post('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    Route::post('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');

    // Payroll
    Route::view('payroll', 'payroll.index')->name('payroll');
    Route::apiResource('salary-components', SalaryComponentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('tax-rules', TaxRuleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('employees/{employee}/salary', [EmployeeSalaryController::class, 'show'])->name('employees.salary.show');
    Route::put('employees/{employee}/salary', [EmployeeSalaryController::class, 'update'])->name('employees.salary.update');

    Route::get('payroll-periods', [PayrollPeriodController::class, 'index'])->name('payroll-periods.index');
    Route::post('payroll-periods', [PayrollPeriodController::class, 'store'])->name('payroll-periods.store');
    Route::get('payroll-periods/{payrollPeriod}', [PayrollPeriodController::class, 'show'])->name('payroll-periods.show');
    Route::post('payroll-periods/{payrollPeriod}/run', [PayrollRunController::class, 'store'])->name('payroll-periods.run');

    Route::get('payroll-runs/{payrollRun}', [PayrollRunController::class, 'show'])->name('payroll-runs.show');
    Route::post('payroll-runs/{payrollRun}/approve', [PayrollRunController::class, 'approve'])->name('payroll-runs.approve');
    Route::post('payroll-runs/{payrollRun}/pay', [PayrollRunController::class, 'pay'])->name('payroll-runs.pay');
    Route::post('payroll-runs/{payrollRun}/lock', [PayrollRunController::class, 'lock'])->name('payroll-runs.lock');

    Route::get('payslips', [PayslipController::class, 'index'])->name('payslips.index');
    Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
    Route::get('payslips/{payslip}/pdf', [PayslipController::class, 'pdf'])->name('payslips.pdf');

    Route::view('loan-centre', 'finance.loans')->name('loan-centre');
    Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
    Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('loans/{loan}/schedule', [LoanController::class, 'schedule'])->name('loans.schedule');
    Route::post('loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
    Route::post('loans/{loan}/cancel', [LoanController::class, 'cancel'])->name('loans.cancel');
    Route::delete('loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');

    // Employee self-service portal
    Route::get('portal', MeDashboardController::class)->name('portal');
    Route::get('me/dashboard', MeDashboardController::class)->name('me.dashboard');
    Route::get('me/profile', [MeProfileController::class, 'show'])->name('me.profile.show');
    Route::put('me/profile', [MeProfileController::class, 'update'])->name('me.profile.update');

    // Documents
    Route::get('employee-documents', [EmployeeDocumentController::class, 'index'])->name('employee-documents.index');
    Route::post('employee-documents', [EmployeeDocumentController::class, 'store'])->name('employee-documents.store');
    Route::get('employee-documents/{document}/download', [EmployeeDocumentController::class, 'download'])->name('employee-documents.download');
    Route::delete('employee-documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employee-documents.destroy');

    // HR requests / tickets
    Route::view('hr-requests-board', 'hr-requests.index')->name('hr-requests');
    Route::get('hr-requests', [HrRequestController::class, 'index'])->name('hr-requests.index');
    Route::post('hr-requests', [HrRequestController::class, 'store'])->name('hr-requests.store');
    Route::get('hr-requests/{hrRequest}', [HrRequestController::class, 'show'])->name('hr-requests.show');
    Route::put('hr-requests/{hrRequest}', [HrRequestController::class, 'update'])->name('hr-requests.update');
    Route::post('hr-requests/{hrRequest}/comments', [HrRequestController::class, 'comment'])->name('hr-requests.comments');

    // Announcements
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])->name('announcements.read');
    Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // Document types + HR letter templates
    Route::view('hr-letters', 'documents.index')->name('hr-letters');
    Route::apiResource('document-types', DocumentTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('document-templates', DocumentTemplateController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('document-templates/{documentTemplate}/preview', [DocumentTemplateController::class, 'preview'])->name('document-templates.preview');
    Route::get('documents/expiring', DocumentExpiryController::class)->name('documents.expiring');
    Route::get('letters', [LetterController::class, 'index'])->name('letters.index');
    Route::post('letters', [LetterController::class, 'store'])->name('letters.store');
    Route::get('letters/{letter}', [LetterController::class, 'show'])->name('letters.show');
    Route::get('letters/{letter}/download', [LetterController::class, 'download'])->name('letters.download');

    // Performance management
    Route::view('performance', 'performance.index')->name('performance');
    Route::apiResource('performance-cycles', PerformanceCycleController::class)
        ->parameters(['performance-cycles' => 'performanceCycle'])
        ->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('performance-cycles/{performanceCycle}/open', [PerformanceCycleController::class, 'open'])->name('performance-cycles.open');
    Route::post('performance-cycles/{performanceCycle}/start-review', [PerformanceCycleController::class, 'startReview'])->name('performance-cycles.start-review');
    Route::post('performance-cycles/{performanceCycle}/close', [PerformanceCycleController::class, 'close'])->name('performance-cycles.close');

    Route::apiResource('goals', GoalController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('goals/{goal}/check-ins', [GoalController::class, 'checkIn'])->name('goals.check-ins');

    Route::apiResource('kpis', KpiController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('kpis/{kpi}/record', [KpiController::class, 'record'])->name('kpis.record');

    // Tasks
    Route::view('task-board', 'tasks.index')->name('tasks');
    Route::apiResource('tasks', TaskController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
    Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('tasks/{task}/cancel', [TaskController::class, 'cancel'])->name('tasks.cancel');

    Route::get('performance-reviews', [PerformanceReviewController::class, 'index'])->name('performance-reviews.index');
    Route::get('performance-reviews/{performanceReview}', [PerformanceReviewController::class, 'show'])->name('performance-reviews.show');
    Route::put('performance-reviews/{performanceReview}', [PerformanceReviewController::class, 'update'])->name('performance-reviews.update');
    Route::post('performance-reviews/{performanceReview}/submit', [PerformanceReviewController::class, 'submit'])->name('performance-reviews.submit');
    Route::post('performance-reviews/{performanceReview}/acknowledge', [PerformanceReviewController::class, 'acknowledge'])->name('performance-reviews.acknowledge');

    // Recruitment / ATS
    Route::view('recruitment', 'recruitment.index')->name('recruitment');
    Route::apiResource('job-positions', JobPositionController::class)
        ->parameters(['job-positions' => 'jobPosition'])
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::apiResource('vacancies', VacancyController::class)->only(['index', 'show', 'store', 'update']);
    Route::post('vacancies/{vacancy}/publish', [VacancyController::class, 'publish'])->name('vacancies.publish');
    Route::post('vacancies/{vacancy}/close', [VacancyController::class, 'close'])->name('vacancies.close');

    Route::apiResource('applicants', ApplicantController::class)->only(['index', 'show', 'store', 'update']);
    Route::get('applicants/{applicant}/resume', [ApplicantController::class, 'resume'])->name('applicants.resume');

    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::post('applications/{application}/stage', [ApplicationController::class, 'moveStage'])->name('applications.stage');
    Route::post('applications/{application}/reject', [ApplicationController::class, 'reject'])->name('applications.reject');
    Route::post('applications/{application}/rate', [ApplicationController::class, 'rate'])->name('applications.rate');
    Route::post('applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])->name('applications.withdraw');
    Route::post('applications/{application}/assign', [ApplicationController::class, 'assign'])->name('applications.assign');

    Route::get('interviews', [InterviewController::class, 'index'])->name('interviews.index');
    Route::post('interviews', [InterviewController::class, 'store'])->name('interviews.store');
    Route::get('interviews/{interview}', [InterviewController::class, 'show'])->name('interviews.show');
    Route::post('interviews/{interview}/feedback', [InterviewController::class, 'feedback'])->name('interviews.feedback');

    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
    Route::post('offers', [OfferController::class, 'store'])->name('offers.store');
    Route::get('offers/{offer}', [OfferController::class, 'show'])->name('offers.show');
    Route::post('offers/{offer}/send', [OfferController::class, 'send'])->name('offers.send');
    Route::post('offers/{offer}/respond', [OfferController::class, 'respond'])->name('offers.respond');
    Route::post('offers/{offer}/rescind', [OfferController::class, 'rescind'])->name('offers.rescind');
    Route::post('offers/{offer}/convert', [OfferController::class, 'convert'])->name('offers.convert');

    // Expenses
    Route::view('expenses', 'finance.expenses')->name('expenses');
    Route::get('expense-claims', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expense-claims', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expense-claims/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::put('expense-claims/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('expense-claims/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::post('expense-claims/{expense}/submit', [ExpenseController::class, 'submit'])->name('expenses.submit');
    Route::post('expense-claims/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expense-claims/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::post('expense-claims/{expense}/reimburse', [ExpenseController::class, 'reimburse'])->name('expenses.reimburse');
    Route::post('expense-claims/{expense}/items', [ExpenseItemController::class, 'store'])->name('expense-items.store');
    Route::put('expense-items/{expenseItem}', [ExpenseItemController::class, 'update'])->name('expense-items.update');
    Route::delete('expense-items/{expenseItem}', [ExpenseItemController::class, 'destroy'])->name('expense-items.destroy');
    Route::get('expense-items/{expenseItem}/receipt', [ExpenseItemController::class, 'receipt'])->name('expense-items.receipt');

    // Asset register
    Route::view('asset-register', 'finance.assets')->name('assets');
    Route::apiResource('assets', AssetController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('assets/{asset}/retire', [AssetController::class, 'retire'])->name('assets.retire');
    Route::post('assets/{asset}/assignments', [AssetAssignmentController::class, 'store'])->name('assets.assignments.store');
    Route::get('asset-assignments', [AssetAssignmentController::class, 'index'])->name('asset-assignments.index');
    Route::post('asset-assignments/{assetAssignment}/return', [AssetAssignmentController::class, 'returnAsset'])->name('asset-assignments.return');

    // Notification centre & preferences
    Route::view('notifications', 'notifications.index')->name('notifications');
    Route::get('notifications/feed', [NotificationController::class, 'index'])->name('notifications.feed');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'show'])->name('me.notification-preferences.show');
    Route::put('me/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('me.notification-preferences.update');

    // Tenant branding
    Route::view('settings/branding', 'settings.branding')->name('settings.branding');
    Route::get('branding', [BrandingController::class, 'show'])->name('branding.show');
    Route::put('branding', [BrandingController::class, 'update'])->name('branding.update');

    // Tenant roles & permissions
    Route::view('settings/permissions', 'settings.permissions')->name('settings.permissions');
    Route::view('my-permissions', 'settings.my-permissions')->name('permissions.mine');

    // Audit trail
    Route::view('audit', 'audit.index')->name('audit');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    // Developer: API tokens & webhooks
    Route::view('developers', 'developers.index')->name('developers');
    Route::get('api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    Route::get('webhook-endpoints', [WebhookEndpointController::class, 'index'])->name('webhook-endpoints.index');
    Route::post('webhook-endpoints', [WebhookEndpointController::class, 'store'])->name('webhook-endpoints.store');
    Route::put('webhook-endpoints/{webhookEndpoint}', [WebhookEndpointController::class, 'update'])->name('webhook-endpoints.update');
    Route::delete('webhook-endpoints/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhook-endpoints.destroy');
    Route::post('webhook-endpoints/{webhookEndpoint}/ping', [WebhookEndpointController::class, 'ping'])->name('webhook-endpoints.ping');
    Route::get('webhook-endpoints/{webhookEndpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('webhook-endpoints.deliveries');

    // Meetings
    Route::view('meetings', 'calls.index')->name('meetings');

    // Staff training / help centre
    Route::view('training', 'training.index')->name('training');

    // Reporting & exports
    Route::view('reports', 'reports.index')->name('reports');
    Route::get('reports/catalogue', [ReportController::class, 'index'])->name('reports.catalogue');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/{report}/print', [ReportController::class, 'print'])->name('reports.print');
    Route::get('dashboard/metrics', DashboardMetricsController::class)->name('dashboard.metrics');
});

/*
| Public per-tenant careers board. Tenant resolved from the request host; no
| auth. Application submission is rate-limited.
*/
Route::middleware(['tenant'])->prefix('careers')->name('careers.')->group(function () {
    Route::get('/', [CareersController::class, 'index'])->name('index');
    Route::get('{vacancy:slug}', [CareersController::class, 'show'])->name('show');
    Route::post('{vacancy:slug}/applications', [CareersController::class, 'apply'])
        ->middleware('throttle:6,1')->name('apply');
});

require __DIR__.'/settings.php';
