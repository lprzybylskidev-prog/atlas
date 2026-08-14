<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Messages;

enum AttachmentKind: string
{
    case File = 'file';
    case Voice = 'voice';
}
