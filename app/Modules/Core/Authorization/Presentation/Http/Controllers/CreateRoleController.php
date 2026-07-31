<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CreateRoleController
{
    public function __construct(
        private UserTeamAuthorizationManager $authorization,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Admin/Authorization/Roles/Create', [
            'permissionOptions' => $this->authorization->permissionOptions(),
        ]);
    }
}
