<?php
declare(strict_types=1);
namespace Themis\Character;

// System
use Themis\System\DatabaseOperator;

// Character
use Themis\Character\TitlerMode;
use Themis\Character\TitlerPayloadBuilder;

// Utils
use Themis\Utils\JsonUtils;

// Exceptions
use Exception;
use JsonException;
use Throwable;
use Themis\Utils\Exceptions\CharacterException;

class CharacterRepository
{
    private ?DatabaseOperator $db = null;
    private ?bool $hasCharacterModeColumn = null;

    public function __construct()
    {
        // When this is called, we're always going to do something in the database.
        $this->db = new DatabaseOperator();
        $this->db->connectToDatabase(); // Start the connection right away.
    }

    public function getCharacterData(int $id): array
    {
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

            if ($this->hasCharacterModeColumn()) {
                $character['character_mode'] = TitlerMode::normalize((string)($character['character_mode'] ?? TitlerMode::NORMAL));
            } else {
                $character['character_mode'] = $this->inferModeFromOptionsJson((string)($character['character_options'] ?? ''));
            }

            return $character;
        } catch (JsonException $e) {
            throw new CharacterException("Failed to decode character data: " . $e->getMessage());
        } catch (Throwable $e) {
            throw new CharacterException("An error occurred while fetching character data: " . $e->getMessage());
        }
    }

    public function createNewCharacter(string $name, array $template, ?string $uuid = null): bool|int
    {
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

            $columns = ["player_id", "character_name", "character_titler"];
            $values = [
                $user['player_id'],
                $name,
                JsonUtils::encode(data: $template, options: JSON_THROW_ON_ERROR)
            ];

            if ($this->hasCharacterModeColumn()) {
                $columns[] = 'character_mode';
                $values[] = TitlerMode::NORMAL;
            }

            $insert = $this->db->insert(
                into: "player_characters",
                columns: $columns,
                values: $values
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
    }

    public function setCharacterMode(int $characterId, string $mode): bool
    {
        if (!$this->db) {
            throw new CharacterException("Database connection is not established.");
        }

        $mode = TitlerMode::normalize($mode);

        try {
            if ($this->hasCharacterModeColumn()) {
                $updated = $this->db->update(
                    table: 'player_characters',
                    columns: ['character_mode'],
                    values: [$mode],
                    where: ['character_id'],
                    equals: [$characterId]
                );
                return $updated === true;
            }

            // Backward compatibility if migration has not been applied yet.
            $character = $this->getCharacterData($characterId);
            $optionsRaw = (string)($character['character_options'] ?? '{}');
            $options = json_decode($optionsRaw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($options)) {
                $options = [];
            }

            $options['afk-ooc'] = $this->modeToLegacyCode($mode);
            $encoded = JsonUtils::encode(data: $options, options: JSON_THROW_ON_ERROR);

            $updated = $this->db->update(
                table: 'player_characters',
                columns: ['character_options'],
                values: [$encoded],
                where: ['character_id'],
                equals: [$characterId]
            );

            return $updated === true;
        } catch (Throwable $e) {
            throw new CharacterException('Failed to set character mode: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function buildTitlerPayload(int $characterId): array
    {
        $character = $this->getCharacterData($characterId);
        $builder = new TitlerPayloadBuilder();
        return $builder->build($character);
    }

    private function hasCharacterModeColumn(): bool
    {
        if ($this->hasCharacterModeColumn !== null) {
            return $this->hasCharacterModeColumn;
        }

        if (!$this->db) {
            $this->hasCharacterModeColumn = false;
            return false;
        }

        $rows = $this->db->manualQuery("SHOW COLUMNS FROM player_characters LIKE 'character_mode'");
        $this->hasCharacterModeColumn = !empty($rows);
        return $this->hasCharacterModeColumn;
    }

    private function inferModeFromOptionsJson(string $optionsRaw): string
    {
        try {
            $options = json_decode($optionsRaw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($options)) {
                return TitlerMode::NORMAL;
            }
            return TitlerMode::fromLegacyAfkOoc($options['afk-ooc'] ?? 0);
        } catch (Throwable) {
            return TitlerMode::NORMAL;
        }
    }

    private function modeToLegacyCode(string $mode): int
    {
        if ($mode === TitlerMode::OOC) {
            return 1;
        }
        if ($mode === TitlerMode::AFK) {
            return 2;
        }
        return 0;
    }

}
