<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Recruitment\PublicJobs;
use Illuminate\View\View;

/**
 * The public job board on the main website (all organizations).
 */
class JobsController extends Controller
{
    public function index(): View
    {
        return view('marketing.jobs.index');
    }

    public function show(PublicJobs $jobs, string $organization, string $vacancy): View
    {
        $job = $jobs->find($organization, $vacancy);

        abort_if($job === null, 404);

        return view('marketing.jobs.show', ['job' => $job]);
    }
}
