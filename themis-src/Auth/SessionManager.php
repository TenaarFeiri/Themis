<?php
declare(strict_types=1);

namespace Themis\Auth;

use Exception;

final class SessionManagerException extends Exception
{
}

final class SessionManager
{
    public function resetForNewAuthentication(): void
    {
        if (headers_sent($file, $line)) {
            throw new SessionManagerException("Cannot manage session: headers already sent in {$file}:{$line}");
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!session_start()) {
                throw new SessionManagerException('Failed to start session for cleanup');
            }
        }

        $_SESSION = [];
        session_unset();

        if (!session_destroy()) {
            themis_error_log('session_destroy() returned false during cleanup');
        }

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? false
            );
        }
    }

    /** @param array<string,mixed> $playerInformation */
    public function startAuthenticatedSession(array $playerInformation): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 48 * 60 * 60,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new SessionManagerException('Failed to start session');
        }

        if (!session_regenerate_id(true)) {
            themis_error_log('session_regenerate_id() returned false');
        }

        $_SESSION['player'] = $playerInformation;
        session_write_close();
    }
}
