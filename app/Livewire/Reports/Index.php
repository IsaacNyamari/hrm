<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Livewire\Concerns\BindsTenant;
use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Services\Reporting\ReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    use BindsTenant;

    #[Url]
    public string $report = '';

    /** @var array<string, string> */
    public array $filters = [];

    public ?string $error = null;

    public function updatedReport(): void
    {
        $this->filters = [];
        $this->error = null;
    }

    public function render(ReportRegistry $registry, ReportService $service): View
    {
        $user = auth()->user();
        $available = $user !== null ? $registry->availableFor($user) : [];

        $selected = $this->report !== '' && isset($available[$this->report]) ? $available[$this->report] : null;
        $result = null;

        if ($selected instanceof Report && $user !== null) {
            try {
                $result = $service->run($selected->key(), $this->filters, $user);
            } catch (AuthorizationException $e) {
                $this->error = $e->getMessage();
            }
        }

        return view('livewire.reports.index', [
            'reports' => $available,
            'selected' => $selected,
            'result' => $result,
        ]);
    }
}
