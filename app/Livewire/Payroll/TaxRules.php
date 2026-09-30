<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Enums\Permission;
use App\Enums\TaxRuleType;
use App\Livewire\Concerns\BindsTenant;
use App\Models\TaxRule;
use App\Services\Payroll\StatutoryRuleWatch;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Statutory rates for this organization (PAYE bands, NSSF, SHIF, Housing Levy,
 * NITA). Every rule is dated, so a legislative change is a new rule rather than
 * an edit to history, and past payroll runs keep the rates they were paid under.
 * The form works in KES and percentages; storage is cents and fractions.
 */
class TaxRules extends Component
{
    use BindsTenant;

    public bool $showForm = false;

    public ?string $editingUuid = null;

    public string $type = 'nssf';

    public string $effective_from = '';

    public ?string $effective_to = null;

    public bool $is_active = true;

    public ?string $rate = null;

    public ?string $employer_rate = null;

    public ?string $ceiling = null;

    public ?string $minimum = null;

    public ?string $amount = null;

    public ?string $relief = null;

    /** @var list<array{upto: ?string, rate: ?string}> */
    public array $bands = [];

    public bool $saved = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', TaxRule::class);
    }

    public function create(): void
    {
        Gate::authorize('create', TaxRule::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function updatedType(): void
    {
        $this->bands = $this->type === TaxRuleType::Paye->value ? [['upto' => null, 'rate' => null], ['upto' => null, 'rate' => null]] : [];
    }

    public function edit(string $uuid): void
    {
        $rule = TaxRule::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $rule);

        $this->resetForm();
        $config = $rule->config;

        $this->editingUuid = $rule->uuid;
        $this->type = $rule->type->value;
        $this->effective_from = $rule->effective_from->toDateString();
        $this->effective_to = $rule->effective_to?->toDateString();
        $this->is_active = $rule->is_active;
        $this->rate = $this->percent($config['rate'] ?? null);
        $this->employer_rate = $this->percent($config['employer_rate'] ?? null);
        $this->ceiling = $this->kes($config['ceiling_cents'] ?? null);
        $this->minimum = $this->kes($config['minimum_cents'] ?? null);
        $this->amount = $this->kes($config['amount_cents'] ?? null);
        $this->relief = $this->kes($config['personal_relief_cents'] ?? null);
        $this->bands = array_map(fn (array $band): array => [
            'upto' => $this->kes($band['upto_cents'] ?? null),
            'rate' => $this->percent($band['rate'] ?? null),
        ], $config['bands'] ?? []);
        $this->showForm = true;
    }

    public function addBand(): void
    {
        $this->bands[] = ['upto' => null, 'rate' => null];
    }

    public function removeBand(int $index): void
    {
        unset($this->bands[$index]);
        $this->bands = array_values($this->bands);
    }

    public function save(Auditor $auditor): void
    {
        $rule = $this->editingUuid !== null ? TaxRule::query()->where('uuid', $this->editingUuid)->firstOrFail() : null;
        Gate::authorize($rule === null ? 'create' : 'update', $rule ?? TaxRule::class);

        $this->validate([
            'type' => ['required', 'in:'.implode(',', array_column(TaxRuleType::cases(), 'value'))],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        $type = TaxRuleType::from($this->type);
        $config = $this->config($type);
        $validated = validator(['config' => $config], $type->configRules())->validate();
        $this->assertBandsAreOrdered($type, $validated['config']);

        $attributes = [
            'name' => $type->label().' (Kenya)',
            'type' => $type->value,
            'country' => 'KE',
            'effective_from' => $this->effective_from,
            'effective_to' => $this->effective_to ?: null,
            'config' => $config,
            'is_active' => $this->is_active,
        ];

        if ($rule === null) {
            $rule = TaxRule::query()->create($attributes);
            $auditor->log('payroll.tax_rule_created', $rule, $attributes);
        } else {
            $before = $rule->only(['effective_from', 'effective_to', 'config', 'is_active']);
            $rule->update($attributes);
            $auditor->log('payroll.tax_rule_updated', $rule, ['before' => $before, 'after' => $attributes]);
        }

        $this->resetForm();
        $this->saved = true;
        $this->dispatch('payroll-updated');
    }

    public function delete(string $uuid, Auditor $auditor): void
    {
        $rule = TaxRule::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $rule);

        $auditor->log('payroll.tax_rule_deleted', $rule, $rule->only(['type', 'effective_from', 'effective_to', 'config']));
        $rule->delete();
        $this->dispatch('payroll-updated');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(StatutoryRuleWatch $watch): View
    {
        return view('livewire.payroll.tax-rules', [
            'rules' => TaxRule::query()->orderBy('type')->orderByDesc('effective_from')->get()->groupBy(fn (TaxRule $rule): string => $rule->type->value),
            'types' => TaxRuleType::cases(),
            'alerts' => $watch->alerts(),
            'canManage' => (bool) auth()->user()?->can(Permission::PayrollManage->value),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingUuid', 'effective_to', 'rate', 'employer_rate', 'ceiling', 'minimum', 'amount', 'relief', 'bands']);
        $this->type = 'nssf';
        $this->effective_from = '';
        $this->is_active = true;
        $this->resetErrorBag();
    }

    /**
     * @return array<string, mixed>
     */
    private function config(TaxRuleType $type): array
    {
        return match ($type) {
            TaxRuleType::Paye => [
                'bands' => array_map(fn (array $band): array => [
                    'upto_cents' => $this->toCents($band['upto'] ?? null),
                    'rate' => $this->toFraction($band['rate'] ?? null),
                ], array_values($this->bands)),
                'personal_relief_cents' => $this->toCents($this->relief),
            ],
            TaxRuleType::Nssf => $this->withoutNulls(['rate' => $this->toFraction($this->rate), 'employer_rate' => $this->toFraction($this->employer_rate), 'ceiling_cents' => $this->toCents($this->ceiling)]),
            TaxRuleType::Shif => $this->withoutNulls(['rate' => $this->toFraction($this->rate), 'minimum_cents' => $this->toCents($this->minimum)]),
            TaxRuleType::HousingLevy => $this->withoutNulls(['rate' => $this->toFraction($this->rate), 'employer_rate' => $this->toFraction($this->employer_rate)]),
            TaxRuleType::Nita => ['amount_cents' => $this->toCents($this->amount)],
            TaxRuleType::Pension => ['rate' => $this->toFraction($this->rate)],
        };
    }

    /**
     * Bands must climb, and only the last may be open-ended, otherwise tax
     * would be charged on the wrong slices.
     *
     * @param  array<string, mixed>  $config
     */
    private function assertBandsAreOrdered(TaxRuleType $type, array $config): void
    {
        if ($type !== TaxRuleType::Paye) {
            return;
        }

        $bands = array_values($config['bands']);
        $previous = 0;

        foreach ($bands as $index => $band) {
            $isLast = $index === array_key_last($bands);
            $upto = $band['upto_cents'] ?? null;

            if (($upto === null) !== $isLast || ($upto !== null && $upto <= $previous)) {
                throw ValidationException::withMessages([
                    'bands' => __('Bands must be in rising order, and only the last band may have no upper limit.'),
                ]);
            }

            $previous = (int) $upto;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutNulls(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => $value !== null);
    }

    private function toCents(?string $kes): ?int
    {
        return $kes === null || trim($kes) === '' || ! is_numeric($kes) ? null : (int) round((float) $kes * 100);
    }

    private function toFraction(?string $percent): ?float
    {
        return $percent === null || trim($percent) === '' || ! is_numeric($percent) ? null : round((float) $percent / 100, 6);
    }

    private function kes(mixed $cents): ?string
    {
        return $cents === null ? null : (string) (($cents / 100) + 0);
    }

    private function percent(mixed $fraction): ?string
    {
        return $fraction === null ? null : (string) (round((float) $fraction * 100, 4) + 0);
    }
}
