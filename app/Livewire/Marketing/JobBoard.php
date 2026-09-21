<?php

declare(strict_types=1);

namespace App\Livewire\Marketing;

use App\Support\Recruitment\PublicJobs;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Searchable list of open jobs across all organizations on the platform.
 */
class JobBoard extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'org', except: '')]
    public string $organization = '';

    #[Url(except: false)]
    public bool $remote = false;

    public function clearFilters(): void
    {
        $this->reset('search', 'organization', 'remote');
    }

    public function render(PublicJobs $jobs): View
    {
        return view('livewire.marketing.job-board', [
            'jobs' => $jobs->list($this->search, $this->organization ?: null, $this->remote),
            'organizations' => $jobs->organizations(),
        ]);
    }
}
