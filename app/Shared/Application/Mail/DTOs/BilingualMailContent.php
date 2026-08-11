<?php

declare(strict_types=1);

namespace App\Shared\Application\Mail\DTOs;

use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

final readonly class BilingualMailContent
{
    /**
     * @param  array{pl: string, en: string}  $subjects
     * @param  array{pl: string, en: string}  $headings
     * @param  array{pl: list<string>, en: list<string>}  $bodyLines
     * @param  array{pl: string, en: string}|null  $actionLabels
     */
    public function __construct(
        public array $subjects,
        public array $headings,
        public array $bodyLines,
        public ?array $actionLabels = null,
        public ?string $actionUrl = null,
    ) {
        if (($actionLabels === null) !== ($actionUrl === null)) {
            throw new InvalidArgumentException('A bilingual mail action requires both labels and a URL.');
        }
    }

    /**
     * @param  list<string>  $bodyKeys
     * @param  array<string, int|string>  $parameters
     */
    public static function fromTranslationKeys(
        string $subjectKey,
        string $headingKey,
        array $bodyKeys,
        ?string $actionKey = null,
        ?string $actionUrl = null,
        array $parameters = [],
    ): self {
        foreach ([$subjectKey, $headingKey, ...$bodyKeys, ...($actionKey === null ? [] : [$actionKey])] as $key) {
            if (! str_starts_with($key, 'mail.') || ! Lang::has($key, 'pl') || ! Lang::has($key, 'en')) {
                throw new InvalidArgumentException(sprintf('Bilingual mail translation key [%s] must exist in Polish and English.', $key));
            }
        }

        $subjects = [];
        $headings = [];
        $bodyLines = [];
        $actionLabels = $actionKey === null ? null : [];

        foreach (['pl', 'en'] as $locale) {
            $subjects[$locale] = trans($subjectKey, $parameters, $locale);
            $headings[$locale] = trans($headingKey, $parameters, $locale);
            $bodyLines[$locale] = array_map(
                static fn (string $key): string => trans($key, $parameters, $locale),
                $bodyKeys,
            );

            if ($actionKey !== null) {
                $actionLabels[$locale] = trans($actionKey, $parameters, $locale);
            }
        }

        /** @var array{pl: string, en: string}|null $actionLabels */
        return new self(
            /** @var array{pl: string, en: string} */ $subjects,
            /** @var array{pl: string, en: string} */ $headings,
            /** @var array{pl: list<string>, en: list<string>} */ $bodyLines,
            $actionLabels,
            $actionUrl,
        );
    }
}
