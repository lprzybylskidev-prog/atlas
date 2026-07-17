<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

final class SensitiveDataRedactor
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_KEY_PATTERNS = [
        '/password/i',
        '/first[-_]?password/i',
        '/token/i',
        '/api[-_]?key/i',
        '/secret/i',
        '/credential/i',
        '/cookie/i',
        '/session/i',
        '/mfa/i',
        '/two[-_]?factor/i',
        '/recovery[-_]?code/i',
        '/authorization/i',
        '/request[-_]?body/i',
        '/response[-_]?body/i',
        '/body/i',
        '/headers?/i',
        '/email/i',
        '/phone/i',
        '/pesel/i',
        '/iban/i',
        '/account[-_]?number/i',
        '/amount/i',
        '/balance/i',
    ];

    public function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->redactArray($value);
        }

        if (is_object($value)) {
            return self::REDACTED;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    public function redactArray(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = $this->redact($value);
        }

        return $redacted;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, mixed>
     */
    public function redactStringKeyedArray(array $values): array
    {
        $redacted = [];

        foreach ($this->redactArray($values) as $key => $value) {
            if (is_string($key)) {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    public function redactText(string $value): string
    {
        $patterns = [
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i',
            '/([?&](?:token|api_key|password|secret)=)[^&\s]+/i',
        ];

        return preg_replace($patterns, self::REDACTED, $value) ?? self::REDACTED;
    }

    private function isSensitiveKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
