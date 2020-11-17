<?php declare(strict_types=1);
namespace Naisdevice\Jita;

/**
 * Get env var as string
 *
 * @param string $key
 * @return string
 */
function env(string $key) : string {
    return trim((string) getenv($key));
}
