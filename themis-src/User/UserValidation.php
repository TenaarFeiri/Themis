<?php
declare(strict_types=1);
namespace Themis\User;

use Themis\System\ThemisContainer;
use Themis\System\DataContainer;
use Themis\System\DatabaseOperator;
use Themis\System\DatabaseOperatorException;
use Exception;

class UserValidationException extends Exception {}
class UserValidation {
    private ThemisContainer $container;
    private DataContainer $dataContainer;
    private bool $debug;
    private ?DatabaseOperator $db = null;
    public function __construct(ThemisContainer $container) {
        $this->container = $container;
        if ($this->container->has('dataContainer')) {
            $this->dataContainer = $this->container->get('dataContainer');
        } else {
            throw new UserValidationException("DataContainer is not bound in ThemisContainer");
        }
        $this->debug = $this->dataContainer->get('debug');
        if ($this->debug) {
            echo "UserValidation initialized with debug mode enabled.\n";
        }

        // Initiate the operator if it is configured in the container.
        if ($this->container->has('databaseOperator')) {
            $this->db = $this->container->get('databaseOperator');
        } else {
            throw new UserValidationException("DatabaseOperator is not bound in ThemisContainer");
        }
    }

    public function checkUserExists(): bool {
        if ($this->debug) {
            echo "Checking if user exists...\n";
        }

        $slHeaders = $this->dataContainer->get('slHeaders');
        $uuid = $slHeaders['HTTP_X_SECONDLIFE_OWNER_KEY'];
        
        $this->db = $this->container->get('databaseOperator');
        $db = $this->db; // For brevity.

        try {
            $db->connectToDatabase();
            if ($db->hasConnection("default") === false) {
                throw new Exception("Failed to connect to default database.");
            }
            if (!$db->isCurrentConnection("default")) {
                $db->useConnection("default"); // Should be used anyway but just in case the fucking faeries cursed us or something...
            }
            $user = $db->select(["*"], "players", ["player_uuid"], [(string)$uuid]);
            if ($this->debug) {
                echo "User: " . print_r($user, true) . "\n";
            }
            
            if (empty($user)) {
                $registerNewUser = $this->registerNewUser($uuid, $slHeaders['HTTP_X_SECONDLIFE_OWNER_NAME']);
                switch ($registerNewUser) {
                    case true:
                        // Do legacy imports if necessary.
                        $legacyImport = $this->container->get('userLegacyImport');
                        if ($legacyImport->importLegacyData()) {
                            // TODO log successful import.
                            if ($this->debug) {
                                echo "Legacy import successful.\n";
                            }
                        }
                        return $registerNewUser;
                        break;
                }
            }
            
            // User exists - check if name needs updating
            $currentName = $slHeaders['HTTP_X_SECONDLIFE_OWNER_NAME'];
            if ($user[0]['player_name'] !== $currentName) {
                $this->updateUserName($uuid, $currentName);
            }
            
        } catch (DatabaseOperatorException $e) {
            if ($this->debug) {
                echo "Error: " . $e->getMessage() . "\n";
            }
            return false;
        }
        return true;
    }

    private function registerNewUser(string $uuid, string $name): bool {
        if ($this->debug) {
            echo "User does not exist. Registering new user...\n";
        }
        
        try {
            $this->db->beginTransaction();
            
            $pstTime = $this->getPstTimestamp();
            $this->db->insert("players", 
                ["player_name", "player_uuid", "player_created", "player_last_online"],
                [(string)$name, (string)$uuid, $pstTime, $pstTime]
            );
            
            $this->db->commitTransaction();
            return $this->verifyRegistration($uuid);
            
        } catch (DatabaseOperatorException $e) {
            $this->db->rollbackTransaction();
            if ($this->debug) {
                echo "Registration failed: " . $e->getMessage() . "\n";
            }
            return false;
        }
    }

    private function verifyRegistration(string $uuid): bool {
        $newUser = $this->db->select(["*"], "players", ["player_uuid"], [(string)$uuid]);
        if (empty($newUser)) {
            if ($this->debug) {
                echo "Registration failed - user not found after insert.\n";
            }
            return false;
        }
        
        if ($this->debug) {
            echo "User registered successfully: " . print_r($newUser, true) . "\n";
        }
        return true;
    }

    private function updateUserName(string $uuid, string $newName): void {
        if ($this->debug) {
            echo "Updating user name to: {$newName}\n";
        }
        
        try {
            $this->db->beginTransaction();
            
            $this->db->update("players", 
                ["player_name"], 
                [(string)$newName], 
                ["player_uuid"], 
                [(string)$uuid]
            );
            
            $this->db->commitTransaction();
            
            if ($this->debug) {
                echo "User name updated successfully.\n";
            }
        } catch (DatabaseOperatorException $e) {
            $this->db->rollbackTransaction();
            if ($this->debug) {
                echo "Failed to update user name: " . $e->getMessage() . "\n";
            }
        }
    }

    private function getPstTimestamp(): string {
        return date('m/d/Y g:i A', time() - (8 * 3600)); // PST is UTC-8
    }
}
