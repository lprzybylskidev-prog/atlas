<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Markdown;

use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

final class SafeMarkdownRenderer implements MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        return strip_tags(
            (string) $this->converter->convert($markdown),
            '<p><br><strong><em><code><pre><ul><ol><li><blockquote><a>',
        );
    }
}
