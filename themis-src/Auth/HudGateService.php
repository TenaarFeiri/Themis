<?php
declare(strict_types=1);

namespace Themis\Auth;

use Exception;
use Themis\System\DatabaseOperator;
use Throwable;

final class HudGateServiceException extends Exception
{
}

final class HudGateService
{
    private LaunchTokenRepository $launchTokens;
    private SessionRepository $sessions;
    private PlayerRepository $players;

    public function __construct(private readonly DatabaseOperator $db)
    {
        $this->launchTokens = new LaunchTokenRepository($db);
        $this->sessions = new SessionRepository($db);
        $this->players = new PlayerRepository($db);
    }

    /** @return array<string,mixed> */
    public function authenticateFromToken(string $token): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/', $token)) {
            throw new HudGateServiceException('Invalid token format');
        }

        $this->db->beginTransaction();
        try {
            $launchToken = $this->launchTokens->findUnusedForUpdate($token);
            if (!is_array($launchToken)) {
                throw new HudGateServiceException('Invalid or already used token.');
            }

            $expiresAt = strtotime((string)($launchToken['expires_at'] ?? ''));
            if ($expiresAt === false || $expiresAt < time()) {
                throw new HudGateServiceException('Token expired.');
            }

            $sessionId = trim((string)($launchToken['session_id'] ?? ''));
            if ($sessionId === '') {
                throw new HudGateServiceException('Token has no associated session.');
            }

            $session = $this->sessions->findActiveBySessionId($sessionId);
            if (!is_array($session)) {
                throw new HudGateServiceException('Session not found, revoked, or expired.');
            }

            $uuid = trim((string)($session['uuid'] ?? ''));
            if ($uuid === '') {
                throw new HudGateServiceException('Session has no player UUID.');
            }

            $sessionRowId = (int)($session['id'] ?? 0);
            if ($sessionRowId <= 0) {
                throw new HudGateServiceException('Session row ID is invalid.');
            }

            $this->launchTokens->markUsed($token);
            $this->sessions->extendExpiryById($sessionRowId, date('Y-m-d H:i:s', strtotime('+24 hours')));
            $this->sessions->revokeOtherSessions($uuid, $sessionRowId);

            $player = $this->players->findByUuid($uuid);
            if (!is_array($player)) {
                throw new HudGateServiceException('Player information not found.');
            }

            $this->db->commitTransaction();
            return $player;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollbackTransaction();
            }
            if ($e instanceof HudGateServiceException) {
                throw $e;
            }
            throw new HudGateServiceException('Authentication failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
