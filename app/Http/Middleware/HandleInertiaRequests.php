<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Diglactic\Breadcrumbs\Breadcrumbs;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'release' => [
                    'version' => config('atlas.release.version'),
                    'id' => config('atlas.release.id'),
                ],
            ],
            'auth' => [
                'user' => $request->user() === null ? null : [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ],
            'locale' => app()->getLocale(),
            'navigation' => [
                'breadcrumbs' => $this->breadcrumbs($request),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    private function breadcrumbs(Request $request): array
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null || ! Breadcrumbs::exists($routeName)) {
            return [];
        }

        $items = [];

        foreach (Breadcrumbs::generate($routeName) as $breadcrumb) {
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
}
