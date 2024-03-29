<?php declare(strict_types=1);

namespace Naisdevice\Jita;

use Naisdevice\Jita\Session\User;

class Session
{
    /**
     * Set a user object
     *
     * @param User $user
     * @return void
     */
    public function setUser(User $user): void
    {
        $_SESSION['user'] = $user;
    }

    /**
     * Get the user instace
     *
     * @return ?User
     */
    public function getUser(): ?User
    {
        $user = $_SESSION['user'] ?? null;

        if (null === $user || !$user instanceof User) {
            $_SESSION['user'] = null;
            return null;
        }

        return $user;
    }

    /**
     * Set a token used to validate a POST
     *
     * @param string $token
     * @return void
     */
    public function setPostToken(string $token): void
    {
        $_SESSION['postToken'] = $token;
    }

    /**
     * Get a post token
     *
     * @return ?string
     */
    public function getPostToken(): ?string
    {
        return array_key_exists('postToken', $_SESSION ?? []) ? (string) $_SESSION['postToken'] : null;
    }

    /**
     * Set the gateway
     *
     * @param ?string $gateway
     * @return void
     */
    public function setGateway(?string $gateway = null): void
    {
        $_SESSION['gateway'] = $gateway;
    }

    /**
     * Get the gateway
     *
     * @return ?string
     */
    public function getGateway(): ?string
    {
        return array_key_exists('gateway', $_SESSION ?? []) ? (string) $_SESSION['gateway'] : null;
    }

    /**
     * Check if a user exists in the session
     *
     * @return bool
     */
    public function hasUser(): bool
    {
        return array_key_exists('user', $_SESSION ?? []) && $_SESSION['user'] instanceof User;
    }

    /**
     * End the current session
     *
     * @codeCoverageIgnore
     * @return void
     */
    public function end(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(
            (string) session_name(),
            '',
            time() - 42000,
            (string) $params['path'],
            (string) $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly'],
        );
        session_destroy();
    }
}
