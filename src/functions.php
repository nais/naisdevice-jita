<?php declare(strict_types=1);

namespace Naisdevice\Jita;

/**
 * Get env var as string
 *
 * If the variable does not exist an empty string is returned. Leading and trailing whitespace is
 * automatically stripped from the returned value.
 */
function env(string $key): string
{
    $value = $_ENV[$key] ?? '';
    return trim((string) $value);
}
