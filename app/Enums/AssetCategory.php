<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetCategory: string
{
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Phone = 'phone';
    case Monitor = 'monitor';
    case Vehicle = 'vehicle';
    case Furniture = 'furniture';
    case SoftwareLicense = 'software_license';
    case AccessCard = 'access_card';
    case Tool = 'tool';
    case Other = 'other';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
