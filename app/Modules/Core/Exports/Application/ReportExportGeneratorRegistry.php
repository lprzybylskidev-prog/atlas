<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportExportGenerator;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Exceptions\ReportExportGeneratorMissing;

final readonly class ReportExportGeneratorRegistry
{
    /**
     * @param  iterable<ReportExportGenerator>  $generators
     */
    public function __construct(private iterable $generators) {}

    public function get(ReportExportFormat $format): ReportExportGenerator
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($format)) {
                return $generator;
            }
        }

        throw ReportExportGeneratorMissing::forFormat($format);
    }
}
