<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Admin;

enum HighRiskAdministrativeOperation: string
{
    case HardDelete = 'hard_delete';
    case IrreversibleAnonymization = 'irreversible_anonymization';
    case MfaReset = 'mfa_reset';
    case AdministratorPermissionChange = 'administrator_permission_change';
    case ImpersonationSensitiveOverride = 'impersonation_sensitive_override';
    case ClosedPeriodTimeTrackingCorrection = 'closed_period_time_tracking_correction';
}
