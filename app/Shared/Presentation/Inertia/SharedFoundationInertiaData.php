<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Inertia;

use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use Diglactic\Breadcrumbs\Breadcrumbs;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

final class SharedFoundationInertiaData implements InertiaSharedDataContributor
{
    public function key(): string
    {
        return 'shared.foundation';
    }

    public function data(Request $request): array
    {
        return [
            'app' => [
                'name' => config('app.name'),
                'release' => [
                    'version' => config('atlas.release.version'),
                    'id' => config('atlas.release.id'),
                ],
            ],
            'locale' => app()->getLocale(),
            'supportedLocales' => ['pl', 'en'],
            'translations' => $this->translations(),
            'navigation.breadcrumbs' => $this->breadcrumbs($request),
            'flash.messages' => $request->session()->get('flash.messages', []),
        ];
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    private function breadcrumbs(Request $request): array
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return [];
        }

        $routeName = $route->getName();

        if ($routeName === null || ! Breadcrumbs::exists($routeName)) {
            return [];
        }

        $items = [];
        $routeParameters = array_values($route->parameters());

        foreach (Breadcrumbs::generate($routeName, ...$routeParameters) as $breadcrumb) {
            if (! is_object($breadcrumb)) {
                continue;
            }

            $attributes = get_object_vars($breadcrumb);
            $title = $attributes['title'] ?? '';
            $url = $attributes['url'] ?? null;

            $items[] = [
                'label' => is_scalar($title) ? (string) $title : '',
                'url' => is_string($url) && $url !== '' ? $url : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function translations(): array
    {
        $path = lang_path(app()->getLocale().'.json');

        if (! is_file($path)) {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            return [];
        }

        $translations = [];

        foreach ($decoded as $key => $value) {
            if (
                is_string($key)
                && is_string($value)
                && preg_match('/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/', $key) === 1
            ) {
                $translations[$key] = $value;
            }
        }

        ksort($translations);

        return $translations;
    }
}
