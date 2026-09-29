<?php

declare(strict_types=1);

namespace App\Livewire\HrRequests;

use App\Enums\HrRequestStatus;
use App\Enums\HrRequestType;
use App\Enums\Permission;
use App\Events\HrRequestSubmitted;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\HrRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

class Board extends Component
{
    use BindsTenant;

    public bool $showForm = false;

    /** @var array<string, string> */
    public array $form = ['type' => 'other', 'subject' => '', 'body' => '', 'priority' => 'normal'];

    public ?string $viewingUuid = null;

    public string $newComment = '';

    public bool $newCommentInternal = false;

    public string $statusValue = '';

    public string $assigneeValue = '';

    public function startCreate(): void
    {
        Gate::authorize('create', HrRequest::class);
        $this->resetErrorBag();
        $this->form = ['type' => 'other', 'subject' => '', 'body' => '', 'priority' => 'normal'];
        $this->showForm = true;
    }

    public function submit(): void
    {
        $data = $this->validate([
            'form.type' => ['required', 'string'],
            'form.subject' => ['required', 'string', 'max:150'],
            'form.body' => ['nullable', 'string', 'max:5000'],
            'form.priority' => ['required', 'in:low,normal,high'],
        ])['form'];

        $employee = auth()->user()?->employee;

        if ($employee === null) {
            Flux::toast(variant: 'danger', text: __('Your account is not linked to an employee record.'));

            return;
        }

        $sequence = HrRequest::query()->count() + 1;

        $hrRequest = HrRequest::query()->create([
            'employee_id' => $employee->getKey(),
            'reference' => sprintf('HR-%06d', $sequence),
            'type' => HrRequestType::from($data['type']),
            'subject' => $data['subject'],
            'body' => $data['body'] !== '' ? $data['body'] : null,
            'priority' => $data['priority'],
            'status' => HrRequestStatus::Open,
        ]);

        HrRequestSubmitted::dispatch($hrRequest);

        $this->showForm = false;
        Flux::toast(variant: 'success', text: __('Request submitted. Reference :ref.', ['ref' => $hrRequest->reference]));
    }

    public function view(string $uuid): void
    {
        $hrRequest = HrRequest::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('view', $hrRequest);

        $this->viewingUuid = $uuid;
        $this->newComment = '';
        $this->newCommentInternal = false;
        $this->statusValue = $hrRequest->status->value;
        $this->assigneeValue = $hrRequest->assigned_to !== null ? (string) $hrRequest->assigned_to : '';
        Flux::modal('view-request')->show();
    }

    public function assignToMe(string $uuid): void
    {
        $hrRequest = HrRequest::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('manage', $hrRequest);

        $hrRequest->assigned_to = auth()->id();
        if ($hrRequest->status === HrRequestStatus::Open) {
            $hrRequest->status = HrRequestStatus::Assigned;
        }
        $hrRequest->save();

        Flux::toast(variant: 'success', text: __('Assigned to you.'));
    }

    public function saveAssignment(): void
    {
        $hrRequest = HrRequest::query()->where('uuid', $this->viewingUuid)->firstOrFail();
        Gate::authorize('manage', $hrRequest);

        $hrRequest->assigned_to = $this->assigneeValue !== '' ? (int) $this->assigneeValue : null;
        if ($hrRequest->assigned_to !== null && $hrRequest->status === HrRequestStatus::Open) {
            $hrRequest->status = HrRequestStatus::Assigned;
            $this->statusValue = HrRequestStatus::Assigned->value;
        }
        $hrRequest->save();

        Flux::toast(variant: 'success', text: __('Assignment updated.'));
    }

    public function saveStatus(): void
    {
        $hrRequest = HrRequest::query()->where('uuid', $this->viewingUuid)->firstOrFail();
        Gate::authorize('manage', $hrRequest);

        $newStatus = HrRequestStatus::from($this->statusValue);
        $hrRequest->status = $newStatus;
        $hrRequest->resolved_at = $newStatus->isTerminal() ? CarbonImmutable::now() : null;
        $hrRequest->save();

        Flux::toast(variant: 'success', text: __('Status updated to :status.', ['status' => $newStatus->label()]));
    }

    public function addComment(): void
    {
        $hrRequest = HrRequest::query()->where('uuid', $this->viewingUuid)->firstOrFail();
        Gate::authorize('comment', $hrRequest);

        $data = $this->validate(['newComment' => ['required', 'string', 'max:5000']]);

        $canSeeInternal = $this->isHr() || $hrRequest->assigned_to === auth()->id();

        $hrRequest->comments()->create([
            'tenant_id' => $hrRequest->tenant_id,
            'user_id' => auth()->id(),
            'body' => $data['newComment'],
            'is_internal' => $this->newCommentInternal && $canSeeInternal,
        ]);

        if ($hrRequest->status === HrRequestStatus::Assigned) {
            $hrRequest->forceFill(['status' => HrRequestStatus::InProgress])->save();
        }

        $this->newComment = '';
        $this->newCommentInternal = false;
        Flux::toast(variant: 'success', text: __('Comment added.'));
    }

    private function isHr(): bool
    {
        return (bool) (auth()->user()?->can('employees.view') || auth()->user()?->can('users.manage'));
    }

    /**
     * @return Collection<int, User>
     */
    private function hrStaff(): Collection
    {
        $tenantId = Tenancy::id();

        if ($tenantId === null) {
            return collect();
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            return User::query()
                ->where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->permission(Permission::EmployeesView->value)->orWhere(fn ($q2) => $q2->permission(Permission::UsersManage->value)))
                ->orderBy('name')
                ->get();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    public function render(): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $isHr = $this->isHr();

        $myRequests = $employee !== null
            ? HrRequest::query()->where('employee_id', $employee->getKey())->latest('id')->get()
            : collect();

        $allRequests = $isHr
            ? HrRequest::query()->with('employee:id,first_name,last_name')->latest('id')->get()
            : collect();

        $assignedToMe = ! $isHr && $user !== null
            ? HrRequest::query()->where('assigned_to', $user->getKey())->with('employee:id,first_name,last_name')->latest('id')->get()
            : collect();

        $viewing = $this->viewingUuid !== null
            ? HrRequest::query()->where('uuid', $this->viewingUuid)->with(['comments.user:id,name', 'employee', 'assignee:id,name'])->first()
            : null;

        $showInternal = $viewing !== null && ($isHr || $viewing->assigned_to === $user?->getKey());

        $visibleComments = $viewing?->comments->filter(fn ($c) => ! $c->is_internal || $showInternal)->values() ?? collect();

        return view('livewire.hr-requests.board', [
            'hasEmployee' => $employee !== null,
            'isHr' => $isHr,
            'myRequests' => $myRequests,
            'allRequests' => $allRequests,
            'assignedToMe' => $assignedToMe,
            'types' => HrRequestType::cases(),
            'statuses' => HrRequestStatus::cases(),
            'viewing' => $viewing,
            'visibleComments' => $visibleComments,
            'showInternal' => $showInternal,
            'canManageViewing' => $viewing !== null && Gate::allows('manage', $viewing),
            'hrStaff' => $isHr ? $this->hrStaff() : collect(),
        ]);
    }
}
