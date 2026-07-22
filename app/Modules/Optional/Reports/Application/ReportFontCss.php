<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use Illuminate\Support\Facades\File;
use RuntimeException;

final readonly class ReportFontCss
{
    public function css(): string
    {
        $regular = $this->font('400:normal');
        $medium = $this->font('500:normal');
        $semibold = $this->font('600:normal');

        return <<<CSS
        @font-face {
            font-family: "Atlas Report";
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url("data:font/woff2;base64,{$regular}") format("woff2");
        }

        @font-face {
            font-family: "Atlas Report";
            font-style: normal;
            font-weight: 500;
            font-display: swap;
            src: url("data:font/woff2;base64,{$medium}") format("woff2");
        }

        @font-face {
            font-family: "Atlas Report";
            font-style: normal;
            font-weight: 600;
            font-display: swap;
            src: url("data:font/woff2;base64,{$semibold}") format("woff2");
        }
        CSS;
    }

    private function font(string $variant): string
    {
        $manifest = $this->manifest();
        $families = $manifest['families'] ?? null;

        if (! is_array($families)) {
            throw new RuntimeException('Report font manifest families are not available.');
        }

        $instrumentSans = $families['instrument-sans'] ?? null;

        if (! is_array($instrumentSans)) {
            throw new RuntimeException('Report font family [instrument-sans] is not available.');
        }

        $variants = $instrumentSans['variants'] ?? null;

        if (! is_array($variants)) {
            throw new RuntimeException('Report font variants are not available.');
        }

        $selectedVariant = $variants[$variant] ?? null;

        if (! is_array($selectedVariant)) {
            throw new RuntimeException(sprintf('Report font variant [%s] is not available.', $variant));
        }

        $files = $selectedVariant['files'] ?? null;

        if (! is_array($files)) {
            throw new RuntimeException(sprintf('Report font variant [%s] is not available.', $variant));
        }

        foreach ($files as $file) {
            if (! is_array($file) || ($file['format'] ?? null) !== 'woff2' || ! is_string($file['file'] ?? null)) {
                continue;
            }

            $path = public_path('build/'.$file['file']);

            if (File::exists($path)) {
                return base64_encode(File::get($path));
            }
        }

        throw new RuntimeException(sprintf('Report font variant [%s] could not be loaded.', $variant));
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $path = public_path('build/fonts-manifest.json');

        if (! File::exists($path)) {
            throw new RuntimeException('Report font manifest is not available.');
        }

        $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Report font manifest must be a JSON object.');
        }

        $manifest = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $manifest[$key] = $value;
            }
        }

        return $manifest;
    }
}
