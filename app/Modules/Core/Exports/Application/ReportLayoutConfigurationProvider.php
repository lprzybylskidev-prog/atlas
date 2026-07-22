<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\DTOs\ReportLayoutConfiguration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

final readonly class ReportLayoutConfigurationProvider
{
    public function get(): ReportLayoutConfiguration
    {
        return new ReportLayoutConfiguration(
            companyName: Config::string('atlas.exports.company.name', 'Atlas'),
            footerText: Config::string('atlas.exports.company.footer', 'Atlas export.'),
            logoDataUri: $this->logoDataUri(),
        );
    }

    private function logoDataUri(): ?string
    {
        $path = Config::string('atlas.exports.company.logo_path', '');

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
