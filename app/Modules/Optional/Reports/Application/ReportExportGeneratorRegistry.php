<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\Reports\Application\Contracts\ReportExportGenerator;
use App\Modules\Optional\Reports\Application\Enums\ReportExportFormat;
use App\Modules\Optional\Reports\Application\Exceptions\ReportExportGeneratorMissing;

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
