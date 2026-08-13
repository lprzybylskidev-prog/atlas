<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

interface MarkdownRenderer
{
    public function render(string $markdown): string;
}
