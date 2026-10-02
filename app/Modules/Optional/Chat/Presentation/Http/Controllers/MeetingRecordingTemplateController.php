<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use Illuminate\Contracts\View\View;

final class MeetingRecordingTemplateController
{
    public function __invoke(): View
    {
        return view('chat.meeting-recording');
    }
}
