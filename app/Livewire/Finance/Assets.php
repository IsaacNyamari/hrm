<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCategory;
use App\Enums\AssetStatus;
use App\Exceptions\AssetException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\Assets\AssetService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Assets extends Component
{
    use BindsTenant;

    public bool $showForm = false;

    public ?string $editingUuid = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showAssignForm = false;

    public string $assignAssetUuid = '';

    public string $assignEmployeeId = '';

    public function startCreate(): void
    {
        Gate::authorize('create', Asset::class);
        $this->resetErrorBag();
        $this->editingUuid = null;
        $this->form = $this->blankForm();
        $this->showForm = true;
    }

    public function startEdit(string $uuid): void
    {
        $asset = Asset::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $asset);

        $this->resetErrorBag();
        $this->editingUuid = $asset->uuid;
        $this->form = [
            'asset_tag' => $asset->asset_tag,
            'name' => $asset->name,
            'category' => $asset->category->value,
            'serial_number' => (string) $asset->serial_number,
            'branch_id' => (string) $asset->branch_id,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.asset_tag' => ['required', 'string', 'max:60'],
            'form.name' => ['required', 'string', 'max:150'],
            'form.category' => ['required', 'string'],
            'form.serial_number' => ['nullable', 'string', 'max:120'],
            'form.branch_id' => ['nullable', 'integer'],
        ])['form'];

        $attributes = [
            'asset_tag' => $data['asset_tag'],
            'name' => $data['name'],
            'category' => AssetCategory::from($data['category']),
            'serial_number' => $data['serial_number'] !== '' ? $data['serial_number'] : null,
            'branch_id' => $data['branch_id'] !== '' ? (int) $data['branch_id'] : null,
        ];

        if ($this->editingUuid === null) {
            Gate::authorize('create', Asset::class);
            Asset::query()->create($attributes + ['status' => AssetStatus::Available]);
            Flux::toast(variant: 'success', text: __('Asset added.'));
        } else {
            $asset = Asset::query()->where('uuid', $this->editingUuid)->firstOrFail();
            Gate::authorize('update', $asset);
            $asset->update($attributes);
            Flux::toast(variant: 'success', text: __('Asset updated.'));
        }

        $this->form = $this->blankForm();
        $this->editingUuid = null;
        $this->showForm = false;
    }

    public function remove(string $uuid): void
    {
        $asset = Asset::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $asset);

        if ($asset->assignments()->exists()) {
            Flux::toast(variant: 'danger', text: __('This asset has an assignment history and cannot be deleted.'));

            return;
        }

        $asset->delete();
        Flux::modal('delete-asset-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Asset deleted.'));
    }

    public function retireAsset(string $uuid, AssetService $service): void
    {
        $asset = Asset::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $asset);

        try {
            $service->retire($asset);
            Flux::modal('retire-asset-'.$uuid)->close();
            Flux::toast(variant: 'success', text: __('Asset retired.'));
        } catch (AssetException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function startAssign(string $uuid): void
    {
        $this->assignAssetUuid = $uuid;
        $this->assignEmployeeId = '';
        $this->showAssignForm = true;
    }

    public function assign(AssetService $service): void
    {
        $asset = Asset::query()->where('uuid', $this->assignAssetUuid)->first();

        if ($asset === null) {
            Flux::toast(variant: 'danger', text: __('Asset not found.'));

            return;
        }

        Gate::authorize('update', $asset);

        $data = $this->validate(['assignEmployeeId' => ['required', 'integer']]);
        $employee = Employee::query()->find((int) $data['assignEmployeeId']);

        if ($employee === null) {
            Flux::toast(variant: 'danger', text: __('Employee not found.'));

            return;
        }

        try {
            $service->assign($asset, $employee, [], auth()->user());
            $this->assignAssetUuid = '';
            $this->assignEmployeeId = '';
            $this->showAssignForm = false;
            Flux::toast(variant: 'success', text: __('Asset assigned to :name.', ['name' => $employee->fullName()]));
        } catch (AssetException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function returnAsset(string $assignmentUuid, AssetService $service): void
    {
        $assignment = AssetAssignment::query()->where('uuid', $assignmentUuid)->with('asset')->first();

        if ($assignment === null) {
            Flux::toast(variant: 'danger', text: __('Assignment not found.'));

            return;
        }

        Gate::authorize('update', $assignment->asset);

        try {
            $service->checkIn($assignment);
            Flux::toast(variant: 'success', text: __('Asset returned.'));
        } catch (AssetException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return ['asset_tag' => '', 'name' => '', 'category' => 'laptop', 'serial_number' => '', 'branch_id' => ''];
    }

    public function render(): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $canManage = $user?->can('assets.view') || $user?->can('assets.manage');
        $canEdit = $user?->can('assets.manage');

        $mine = $employee !== null
            ? AssetAssignment::query()
                ->where('employee_id', $employee->getKey())
                ->where('status', AssetAssignmentStatus::Active->value)
                ->with('asset')
                ->get()
            : collect();

        $register = $canManage
            ? Asset::query()->with('activeAssignments.employee:id,first_name,last_name')->orderBy('asset_tag')->limit(200)->get()
            : collect();

        return view('livewire.finance.assets', [
            'canManage' => $canManage,
            'canEdit' => $canEdit,
            'mine' => $mine,
            'register' => $register,
            'categories' => AssetCategory::cases(),
            'branches' => $canEdit ? Branch::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'employees' => $canEdit ? Employee::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']) : collect(),
        ]);
    }
}
