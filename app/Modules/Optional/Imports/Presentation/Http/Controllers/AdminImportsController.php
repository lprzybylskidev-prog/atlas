<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;

final readonly class AdminImportsController
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.managed-processes.imports.index');
    }
}
