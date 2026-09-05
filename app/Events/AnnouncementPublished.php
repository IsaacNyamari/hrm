<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Foundation\Events\Dispatchable;

class AnnouncementPublished
{
    use Dispatchable;

    public function __construct(public readonly Announcement $announcement) {}
}
