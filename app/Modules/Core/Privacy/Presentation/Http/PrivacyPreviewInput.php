<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Presentation\Http;

final class PrivacyPreviewInput
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_\\-]*$/'],
            'subject_identifier' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:12', 'max:2000'],
            'dry_run' => ['sometimes', 'boolean', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'subject_type' => __('validation.attributes.privacy_subject_type'),
            'subject_identifier' => __('validation.attributes.privacy_subject_identifier'),
            'reason' => __('validation.attributes.reason'),
            'dry_run' => __('validation.attributes.dry_run'),
        ];
    }
}
