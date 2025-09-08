<?php
declare(strict_types=1);
namespace Themis\User;

use Themis\System\ThemisContainer;
use Themis\System\DatabaseOperator;
use Themis\System\DatabaseOperatorException;
use Themis\Utils\Exceptions\UserValidationException;
use Exception;
use PDOException;

/**
 * Lightweight, container-aware user validation helper.
 *
 * Responsibilities:
 * - Keep a reference to ThemisContainer for optional helpers (legacy import, logging)
 * - Provide methods that accept explicit input (uuid, name) and an optional DatabaseOperator
 * - Never reach into headers or the DataContainer; the caller provides needed data
 */
class UserValidation {
    private ThemisContainer $container;
    private ?DatabaseOperator $db = null;
    private bool $debug = false;

    /**
     * Keep the container available for optional helpers. Do not wire DataContainer here.
     */
    public function __construct(ThemisContainer $container, bool $debug = false) {
        $this->container = $container;
        // Each UserValidation instance owns its own DatabaseOperator so
        // transactional work here will not collide with caller transactions.
        $this->db = new DatabaseOperator();
        $this->debug = $debug;
    }

    /**
     * Check whether a player exists by UUID and ensure the stored name is current.
     * If the player does not exist this will attempt to register them (transaction handled here).
     *
     * Inputs are explicit; no global headers or DataContainer access.
     *
     * @param string $uuid Player UUID
     * @param string $name Display name that should be persisted
     * @param DatabaseOperator|null $db Optional DatabaseOperator; falls back to container if unset
     * @return bool True on success (user exists or was created), false on error
     * @throws UserValidationException When required dependencies are missing
     */
    public function checkUserExists(string $uuid, string $name): bool {

        $db = $this->db;
        if (!$db) {
            throw new UserValidationException("DatabaseOperator not available on this UserValidation instance.");
        }

        try {
            $db->connectToDatabase();
            if ($db->hasConnection(connectionName: "default") === false) {
                throw new UserValidationException("Failed to connect to default database.");
            }
            if (!$db->isCurrentConnection(connectionName: "default")) {
                $db->useConnection(connectionName: "default");
            }

            $user = $db->select(select: ["*"], from: "players", where: ["player_uuid"], equals: [(string)$uuid]);

            if (empty($user)) {
                // Attempt to create the user here using this instance's operator.
                $created = $this->registerNewUser(uuid: $uuid, name: $name);
                if (!$created) {
                    return false;
                }

                // Optional legacy import if the container provides it
                // TODO: Refactor.
                /*if ($this->container->has('userLegacyImport')) {
                    $legacy = $this->container->get('userLegacyImport');
                    if (is_object($legacy) && method_exists($legacy, 'importLegacyData')) {
                        try {
                            $legacy->importLegacyData();
                            if ($this->debug) {
                                echo "Legacy import attempted after new user creation.\n";
                            }
                        } catch (\Throwable $e) {
                            if ($this->debug) {
                                echo "Legacy import failed: " . $e->getMessage() . "\n";
                            }
                            // Non-fatal: legacy import failure doesn't change registration outcome.
                        }
                    }
                }*/

                return true;
            }

            // If user exists, ensure the name is up-to-date
            $currentName = (string)$name;
            if ($user[0]['player_name'] !== $currentName) {
                $this->updateUserName(uuid: $uuid, newName: $currentName);
            }

            return true;
        } catch (DatabaseOperatorException $e) {
            if ($this->debug) {
                echo "Database error in checkUserExists: " . $e->getMessage() . "\n";
            }
            return false;
        }
    }

    /**
     * Register a new player row. This method will start and commit/rollback its own transaction
     * if the provided DatabaseOperator is not already in a transaction.
     *
     * @param string $uuid
     * @param string $name
     * @return bool
     * @throws UserValidationException
     */
    public function registerNewUser(string $uuid, string $name): bool {
        $db = $this->db;
        if (!$db) {
            throw new UserValidationException("DatabaseOperator not available on this UserValidation instance.");
        }

        $startedTransaction = false;
        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $startedTransaction = true;
            }

            $pstTime = $this->getPstTimestamp();
            $db->insert(
                into: "players",
                columns: ["player_name", "player_uuid", "player_created", "player_last_online"],
                values: [(string)$name, (string)$uuid, $pstTime, $pstTime]
            );

            // Verify insertion
            $newUser = $db->select(select: ["*"], from: "players", where: ["player_uuid"], equals: [(string)$uuid]);
            if (empty($newUser)) {
                if ($startedTransaction) {
                    $db->rollbackTransaction();
                }
                if ($this->debug) {
                    echo "Registration verification failed for {$uuid}\n";
                }
                return false;
            }

            if ($startedTransaction) {
                $db->commitTransaction();
            }

            if ($this->debug) {
                echo "User registered: " . print_r($newUser[0], true) . "\n";
            }

            return true;
        } catch (DatabaseOperatorException $e) {
            if ($this->debug) {
                echo "Database error during registerNewUser: " . $e->getMessage() . "\n";
            }
            try {
                if ($db->inTransaction()) {
                    $db->rollbackTransaction();
                }
            } catch (DatabaseOperatorException $_) {
                // swallow secondary errors
            }
            return false;
        }
    }

    /**
     * Update a player's name in a safe transactional manner.
     */
    private function updateUserName(string $uuid, string $newName): void {
        $db = $this->db;
        if (!$db) {
            throw new UserValidationException("DatabaseOperator not available on this UserValidation instance.");
        }

        if ($this->debug) {
            echo "Updating name for {$uuid} -> {$newName}\n";
        }

        try {
            $started = false;
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $started = true;
            }

            $db->update(
                table: "players",
                columns: ["player_name"],
                values: [(string)$newName],
                where: ["player_uuid"],
                equals: [(string)$uuid]
            );

            if ($started) {
                $db->commitTransaction();
            }

            if ($this->debug) {
                echo "Name update committed for {$uuid}\n";
            }
        } catch (DatabaseOperatorException $e) {
            try {
                if ($db->inTransaction()) {
                    $db->rollbackTransaction();
                }
            } catch (DatabaseOperatorException $_) {
                // swallow
            }
            if ($this->debug) {
                echo "Failed to update name for {$uuid}: " . $e->getMessage() . "\n";
            }
        }
    }

    private function getPstTimestamp(): string {
        return date('m/d/Y g:i A', time() - (8 * 3600)); // PST is UTC-8
    }
}
