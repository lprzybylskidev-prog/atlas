<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\Reports\Application\DTOs\ReportLayoutConfiguration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

final readonly class ReportLayoutConfigurationProvider
{
    public function get(): ReportLayoutConfiguration
    {
        return new ReportLayoutConfiguration(
            companyName: Config::string('atlas.reports.company.name', 'Atlas'),
            footerText: Config::string('atlas.reports.company.footer', 'Atlas report export.'),
            logoDataUri: $this->logoDataUri(),
        );
    }

    private function logoDataUri(): ?string
    {
        $path = Config::string('atlas.reports.company.logo_path', '');

        if ($path === '') {
            return null;
        }

        $absolutePath = public_path($path);

        if (! File::exists($absolutePath)) {
            return null;
        }

        $mimeType = File::mimeType($absolutePath) ?: 'image/svg+xml';

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode(File::get($absolutePath)));
    }
}
