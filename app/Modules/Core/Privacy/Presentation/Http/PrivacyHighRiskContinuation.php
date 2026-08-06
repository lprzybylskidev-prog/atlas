<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Presentation\Http;

use App\Shared\Presentation\Http\Contracts\HighRiskReauthenticationContinuation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class PrivacyHighRiskContinuation implements HighRiskReauthenticationContinuation
{
    public const RECOVERABLE_INPUT = 'atlas_privacy_high_risk_recoverable_input';

    public function supports(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'admin.privacy-retention.hard-delete.preview',
            'admin.privacy-retention.anonymization.preview',
        ], true);
    }

    public function validate(Request $request): void
    {
        Validator::make($request->all(), PrivacyPreviewInput::rules(), [], PrivacyPreviewInput::attributes())->validate();
    }

    public function preserve(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $fields = [
            'operation',
            'subject_type',
            'subject_identifier',
            'reason',
            'dry_run',
        ];

        $request->flashOnly($fields);
        $request->session()->put(self::RECOVERABLE_INPUT, $request->only($fields));
    }
}
