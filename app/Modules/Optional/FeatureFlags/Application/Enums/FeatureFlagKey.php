<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Enums;

enum FeatureFlagKey: string
{
    case ReportsPreview = 'reports.preview';

    case PrivacyWorkflowPreview = 'privacy.workflow_preview';

    case TimeTrackingPreview = 'time_tracking.preview';
}
