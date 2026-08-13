<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use App\Modules\Optional\Chat\Infrastructure\Markdown\SafeMarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class SafeMarkdownRendererTest extends TestCase
{
    public function test_it_supports_markdown_lite_without_raw_html_or_unsafe_links(): void
    {
        $html = (new SafeMarkdownRenderer)->render(<<<'MARKDOWN'
**bold** *italic* `code`

```php
echo 'safe';
```

- one
- two

> quote

[safe](https://atlas.example) [unsafe](javascript:alert(1))

<img src=x onerror=alert(1)>

![remote image](https://atlas.example/tracker.png)
MARKDOWN);

        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<em>italic</em>', $html);
        self::assertStringContainsString('<code>code</code>', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringContainsString('https://atlas.example', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('tracker.png', $html);
        self::assertStringNotContainsString('onerror', $html);
    }
}
