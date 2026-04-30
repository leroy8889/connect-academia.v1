<?php
declare(strict_types=1);

namespace Core;

class Session
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function userRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function user(): array
    {
        return [
            'id'    => $_SESSION['user_id']    ?? null,
            'uuid'  => $_SESSION['user_uuid']  ?? null,
            'role'  => $_SESSION['user_role']  ?? null,
            'name'  => $_SESSION['user_name']  ?? null,
            'photo' => $_SESSION['user_photo'] ?? null,
        ];
    }

    // ── Admin ─────────────────────────────────────────────────────────────
    public static function isAdmin(): bool
    {
        return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_2fa_ok']);
    }

    public static function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    public static function adminRole(): ?string
    {
        return $_SESSION['admin_role'] ?? null;
    }
}
