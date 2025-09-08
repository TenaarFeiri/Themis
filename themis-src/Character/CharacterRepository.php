<?php
declare(strict_types=1);
namespace Themis\Character;

// System
use Themis\System\DatabaseOperator;

// Utils
use Themis\Utils\JsonUtils;

// Exceptions
use Exception;
use JsonException;
use Throwable;
use Themis\Utils\Exceptions\CharacterException;

class CharacterRepository {
    private ?DatabaseOperator $db = null;

    public function __construct() {
        // When this is called, we're always going to do something in the database.
        $this->db = new DatabaseOperator();
        $this->db->connectToDatabase(); // Start the connection right away.
    }

    public function getCharacterData(int $id): array {
        if (!$this->db) {
            throw new CharacterException("Database connection is not established.");
        }
        try {
            if ($this->db->hasConnection("default") === false) {
                throw new Exception("Failed to properly connect to database.");
            }
            $character = $this->db->select(select: ["*"], from: "player_characters", where: ["character_id"], equals: [$id]);
            if (empty($character)) {
                throw new CharacterException("Character with ID $id not found.");
            }
            if (count($character) > 1) {
                throw new CharacterException("Multiple characters found with the same ID: $id");
            }
            $character = $character[0]; // We only want the first result.
            return $character;
        } catch (JsonException $e) {
            throw new CharacterException("Failed to decode character data: " . $e->getMessage());
        } catch (Throwable $e) {
            throw new CharacterException("An error occurred while fetching character data: " . $e->getMessage());
        }
    }

    public function createNewCharacter(string $name, array $template, ?string $uuid = null): bool | int {
        // Returns last insert id on success.
        if (!$this->db) {
            throw new CharacterException("Database connection is not established.");
        }

        $user = null;
        if ($uuid !== null) { // Normally we don't pass a UUID, but in some cases (like CLI scripts) we might.
            $user = $this->db->select(select: ["*"], from: "players", where: ["player_uuid"], equals: [$uuid]);
            if (empty($user) || !isset($user[0]['player_id'])) {
                throw new CharacterException("No player found with UUID: $uuid");
            }
            $user = $user[0]; // Equivalent to $_SESSION['player'].
        } else {
            if (!isset($_SESSION['player']) || !isset($_SESSION['player']['player_uuid'])) {
                throw new CharacterException("No player information found in session.");
            }
            $user = $_SESSION['player'];
        }

        $template["name"] = $name; // Set the character name in the template.

        try {
            if ($this->db->hasConnection(connectionName: "default") === false) {
                throw new Exception("Failed to properly connect to database.");
            }
            $this->db->beginTransaction();
            $insert = $this->db->insert(
                into: "player_characters",
                columns: ["player_id", "character_name", "character_titler"],
                values: [
                    $user['player_id'],
                    $name,
                    JsonUtils::encode(data: $template, options: JSON_THROW_ON_ERROR)
                ]
            );
            if ($insert === false) {
                $this->db->rollbackTransaction();
                throw new CharacterException("Failed to insert new character into database. Also HOW?! It shouldn't be possible to get here.");
            }
            $this->db->commitTransaction();
            return $insert; // Last insert ID
        } catch (Throwable $e) {
            $this->db->rollbackTransaction();
            throw new CharacterException("An error occurred while creating a new character: " . $e->getMessage());
        }
        return false;
    }

}
