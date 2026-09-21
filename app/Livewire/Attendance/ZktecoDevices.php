<?php

declare(strict_types=1);

namespace App\Livewire\Attendance;

use App\Exceptions\ZktecoException;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\Zkteco\ZktecoDeviceService;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ZktecoDevices extends Component
{
    use BindsTenant;

    public bool $showForm = false;

    /** @var array<string, string> */
    public array $form = ['serial_number' => '', 'name' => ''];

    public string $renamingSerial = '';

    public string $renameValue = '';

    public string $managingSerial = '';

    public string $fingerprintsEmployeeId = '';

    public string $cardEmployeeId = '';

    public string $cardValue = '';

    public string $viewingUsersSerial = '';

    public function mount(): void
    {
        Gate::authorize('create', Attendance::class);
    }

    public function startCreate(): void
    {
        $this->resetErrorBag();
        $this->form = ['serial_number' => '', 'name' => ''];
        $this->showForm = true;
    }

    public function claim(ZktecoDeviceService $service): void
    {
        $data = $this->validate([
            'form.serial_number' => ['required', 'string', 'max:64'],
            'form.name' => ['nullable', 'string', 'max:120'],
        ])['form'];

        try {
            $service->claim(Tenancy::getOrFail()->id, $data['serial_number'], $data['name'] !== '' ? $data['name'] : null);
            $this->showForm = false;
            Flux::toast(variant: 'success', text: __('Device registered. It will appear as online once it connects.'));
        } catch (ZktecoException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function startRename(string $serial): void
    {
        $device = $this->deviceOrFail($serial);
        $this->renamingSerial = $device->serial_number;
        $this->renameValue = (string) $device->name;
        Flux::modal('rename-device')->show();
    }

    public function rename(ZktecoDeviceService $service): void
    {
        $device = $this->deviceOrFail($this->renamingSerial);
        $data = $this->validate(['renameValue' => ['required', 'string', 'max:120']]);

        $service->rename($device, $data['renameValue']);
        $this->renamingSerial = '';
        Flux::modal('rename-device')->close();
        Flux::toast(variant: 'success', text: __('Device renamed.'));
    }

    public function release(string $serial, ZktecoDeviceService $service): void
    {
        $device = $this->deviceOrFail($serial);
        $service->release($device);
        Flux::modal('release-device-'.$serial)->close();
        Flux::toast(variant: 'success', text: __('Device removed from your company.'));
    }

    public function sendCheck(string $serial, ZktecoDeviceService $service): void
    {
        $service->sendCheckCommand($this->deviceOrFail($serial));
        Flux::toast(variant: 'success', text: __('Connectivity check queued — it will run next time the device polls in.'));
    }

    public function sendReboot(string $serial, ZktecoDeviceService $service): void
    {
        $service->sendRebootCommand($this->deviceOrFail($serial));
        Flux::modal('reboot-device-'.$serial)->close();
        Flux::toast(variant: 'success', text: __('Reboot queued.'));
    }

    public function queryUsers(string $serial, ZktecoDeviceService $service): void
    {
        $service->sendQueryUsersCommand($this->deviceOrFail($serial));
        $this->viewingUsersSerial = $serial;
        Flux::modal('device-users')->show();
        Flux::toast(variant: 'success', text: __('Asked the device to report its enrolled users — this list will fill in as they arrive.'));
    }

    public function manageEmployees(string $serial): void
    {
        $this->deviceOrFail($serial);
        $this->managingSerial = $serial;
        Flux::modal('manage-employees')->show();
    }

    public function syncEmployee(int $employeeId, ZktecoDeviceService $service): void
    {
        $device = $this->deviceOrFail($this->managingSerial);
        $employee = Employee::query()->findOrFail($employeeId);

        $service->syncEmployee($device, $employee);
        Flux::toast(variant: 'success', text: __(':name queued to enrol on this device.', ['name' => $employee->fullName()]));
    }

    public function unsyncEmployee(int $employeeId, ZktecoDeviceService $service): void
    {
        $device = $this->deviceOrFail($this->managingSerial);
        $employee = Employee::query()->findOrFail($employeeId);

        $service->removeEmployee($device, $employee);
        Flux::toast(variant: 'success', text: __(':name queued for removal from this device.', ['name' => $employee->fullName()]));
    }

    /**
     * Generate a fresh keypad password for the employee and push it to every
     * device they're registered on — a fallback verification method for
     * whenever fingerprint isn't available.
     */
    public function regeneratePassword(int $employeeId, ZktecoDeviceService $service): void
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $password = $service->regenerateDevicePassword($employee);

        Flux::toast(variant: 'success', text: __('New device password for :name is :password — share it with them directly.', [
            'name' => $employee->fullName(),
            'password' => $password,
        ]));
    }

    public function startEditCard(int $employeeId): void
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $this->cardEmployeeId = (string) $employeeId;
        $this->cardValue = (string) $employee->device_card_number;
        Flux::modal('manage-employees')->close();
        Flux::modal('edit-card')->show();
    }

    public function saveCardNumber(ZktecoDeviceService $service): void
    {
        $data = $this->validate(['cardValue' => ['nullable', 'string', 'max:32']]);
        $employee = Employee::query()->findOrFail((int) $this->cardEmployeeId);

        $service->setCardNumber($employee, $data['cardValue'] !== '' ? $data['cardValue'] : null);

        $this->cardEmployeeId = '';
        Flux::modal('edit-card')->close();
        Flux::toast(variant: 'success', text: __('Card number saved and pushed to every device :name is on.', ['name' => $employee->fullName()]));
    }

    public function manageFingerprints(int $employeeId): void
    {
        $this->fingerprintsEmployeeId = (string) $employeeId;
        Flux::modal('manage-employees')->close();
        Flux::modal('manage-fingerprints')->show();
    }

    /**
     * Push a fingerprint captured at one device out to every other device the
     * company has registered — "enrol once, recognised everywhere".
     */
    public function pushFingerprintEverywhere(int $employeeId, int $fingerIndex, ZktecoDeviceService $service): void
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $template = $service->fingerprintsFor($employee)[(string) $fingerIndex]['template'] ?? null;

        if ($template === null) {
            Flux::toast(variant: 'danger', text: __('That fingerprint is no longer on file.'));

            return;
        }

        $capturedOnDeviceId = $service->fingerprintsFor($employee)[(string) $fingerIndex]['device_id'] ?? null;
        $count = 0;

        foreach ($service->devicesForTenant(Tenancy::getOrFail()->id) as $device) {
            if ($device->id === $capturedOnDeviceId) {
                continue;
            }

            $service->pushFingerprintTemplate($device, $employee, $fingerIndex, $template);
            $count++;
        }

        Flux::toast(variant: 'success', text: __('Queued on :count other device(s).', ['count' => $count]));
    }

    public function deleteFingerprintEverywhere(int $employeeId, int $fingerIndex, ZktecoDeviceService $service): void
    {
        $employee = Employee::query()->findOrFail($employeeId);

        foreach ($service->devicesForTenant(Tenancy::getOrFail()->id) as $device) {
            $service->deleteFingerprint($device, $employee, $fingerIndex);
        }

        Flux::modal('delete-fingerprint-'.$fingerIndex)->close();
        Flux::toast(variant: 'success', text: __('Fingerprint removed and delete queued on every device.'));
    }

    private function deviceOrFail(string $serial): ZktecoDevice
    {
        $device = ZktecoDevice::query()->forTenant(Tenancy::getOrFail()->id)->where('serial_number', $serial)->first();

        abort_if($device === null, 404);

        return $device;
    }

    public function render(ZktecoDeviceService $service): View
    {
        $tenantId = Tenancy::getOrFail()->id;
        $allDevices = $service->devicesForTenant($tenantId);

        $devices = $allDevices->map(fn (ZktecoDevice $d) => [
            'device' => $d,
            'online' => $d->isOnline(),
            'pending' => $service->pendingCommandCount($d),
        ]);

        $fingerprintsEmployee = $this->fingerprintsEmployeeId !== ''
            ? Employee::query()->find((int) $this->fingerprintsEmployeeId)
            : null;

        $viewingUsersDevice = $this->viewingUsersSerial !== ''
            ? $allDevices->firstWhere('serial_number', $this->viewingUsersSerial)
            : null;

        $rawDeviceUsers = $viewingUsersDevice !== null
            ? ZktecoUser::query()->where('device_id', $viewingUsersDevice->id)->orderBy('name')->get()
            : collect();

        // Only show users that match an employee in this system — a device
        // can carry its own admin/installer accounts or leftovers from
        // before it was claimed, which have nothing to do with this company.
        $deviceUserEmployeeNames = Employee::query()
            ->whereIn('id', $rawDeviceUsers->pluck('pin')->map(fn ($pin) => (int) $pin))
            ->get()
            ->mapWithKeys(fn (Employee $e) => [(string) $e->id => $e->fullName()]);

        $deviceUsers = $rawDeviceUsers->filter(fn (ZktecoUser $u) => $deviceUserEmployeeNames->has($u->pin))->values();

        return view('livewire.attendance.zkteco-devices', [
            'devices' => $devices,
            'employees' => Employee::query()->active()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'device_password', 'device_card_number']),
            'fingerprintsEmployee' => $fingerprintsEmployee,
            'fingerprints' => $fingerprintsEmployee !== null ? $service->fingerprintsFor($fingerprintsEmployee) : [],
            'deviceNames' => $allDevices->pluck('name', 'id'),
            'viewingUsersDevice' => $viewingUsersDevice,
            'deviceUsers' => $deviceUsers,
            'deviceUserEmployeeNames' => $deviceUserEmployeeNames,
        ]);
    }
}
