<?php
declare(strict_types=1);
namespace Themis\Character;

use Themis\System\ThemisContainer;
use Themis\System\DataContainer;
use Themis\System\DatabaseOperator;


use Themis\Utils\StringUtils;

use Exception;
use JsonException;

class CharacterException extends Exception {}
class CharacterImportException extends Exception {
    protected $message = "Character import error occurred: %s";

    public function __construct(string $message) {
        $this->message = sprintf($this->message, $message);
        parent::__construct($this->message);
    }
}
class Character {
    private ?ThemisContainer $container;
    private ?DataContainer $dataContainer;
    private ?DatabaseOperator $dbOperator;

    private const ATTACH_POINTS_FILE = "secondlife_attachment_points.json";

    private bool $isImporting = false;

    private array $defaultCharacterTitlerTemplate = [
        // Constant => Title
        "Name" => "My name.", // Always character's name
        "Species" => "My species.",
        "Mood" => "My mood.",
        "Status" => "My status.",
        "Scent" => "My scent.",
        "Currently" => "Idle.",
        "template" => "character" // ALWAYS bottom
    ];

    private array $defaultCharacterSettingsTemplate = [
        "color" => "<255,255,255>", // Text colour
        "opacity" => 1.0, // Fully opaque
        "attach_point" => "head", // Attachment point, head
        "position" => "<0,0,0>", // Offset from attachment point.
        "afk-ooc" => 0, // 0 = present, 1 = ooc, 2 = afk
        "afk-msg" => "", // Display message when AFK. Default to empty.
        "ooc-msg" => "", // Display message when OOC. Default to empty.
        "template" => "settings" // ALWAYS bottom
    ];

    private array $defaultCharacterStatsTemplate = [
        "health" => 1, // Health, modified later by constitution and character archetype
        "strength" => 10, // Strength
        "dexterity" => 10, // Dexterity
        "constitution" => 10, // Constitution
        "magic" => 100, // Magic
        "class" => 0, // Class (by ID)
        "template" => "stats" // ALWAYS bottom
    ];

    public function __construct(ThemisContainer $container) {
        $this->container = $container;
        if (!$this->container->has('dataContainer')) {
            throw new CharacterException("DataContainer is not bound in ThemisContainer.");
        }
        $this->dataContainer = $container->get('dataContainer');
        if (!$this->dataContainer->has('cmd') && !$this->dataContainer->has('importing')) {
            throw new CharacterException("Command data is not set in DataContainer.");
        }
        if ($this->dataContainer->has('importing')) {
            $this->isImporting = $this->dataContainer->get('importing') ?? false;
        }
        $this->dbOperator = $container->get('databaseOperator');
    }

    public function run(): array {
        return [];
    }

    public function importLegacyCharacters(array $legacyCharacters): bool {
        switch ($this->isImporting) {
            case false:
                throw new CharacterImportException("Legacy importing was attempted but has not been explicitly set up.");
                break;
        }

        if (count($legacyCharacters) === 0) {
            throw new CharacterImportException("List empty. No legacy characters to import. Why are we here? This shouldn't have happened.");
        }
        return true;
    }

    private function createCharacter(string $name, int $attach_point = 0, int $legacy = 0): array {
        $character = $this->defaultCharacterTitlerTemplate;
        $settings = $this->defaultCharacterSettingsTemplate;
        if ($attach_point !== 0) {
            $settings['attach_point'] = $attach_point;
        }
        $character[0] = $name;
        return $character;
    }

    private function loadCharacter(int $characterId): string {
        if ($characterId === 0) {
            throw new CharacterException("Invalid character ID.");
        }
        $isLegacy = false;
        if ($characterId < 0) {
            // Loading a legacy character.
            // This should never happen from the user side; if we're here then the system is most likely
            // automatically loading a legacy character from a character import.
            $isLegacy = true;
            // Then remove the negative symbol from characterId as we're using that to look for legacy id.
            $characterId = abs($characterId);
        }
        // Fetch the character! We need to get a few things first.
        $db = $this->dbOperator;
        try {
            $db->connectToDatabase();
            if (!$db->hasConnection("default")) {
                throw new CharacterException("Failed to connect to default database.");
            }
            if (!$db->isCurrentConnection("default")) {
                $db->useConnection("default");
            }

            // Now we get the player's ID from the data container, as it should already be set.
            // But just in case...
            if (!$this->dataContainer->has('userData')) {
                throw new CharacterException("User data is not set.");
            }
            $userData = $this->dataContainer->get('userData');
                $playerId = $userData['player_id'] ?? null;
                if ($playerId === null) {
                    throw new CharacterException("Player ID is not set. How?! Shouldn't be possible if we're here!");
            }
            
            $characterData = [];
            switch ($isLegacy) {
                
                case true:
                    // Legacy character loading.
                    $characterData = $db->select(["*"], "player_characters", ["player_id", "legacy"], [$playerId, $characterId]);
                    if (empty($characterData)) {
                        throw new CharacterException("Legacy character was requested by system, but character was not found.");
                    }
                    break;

                default:
                    // Regular character loading.
                    $characterData = $db->select(["*"], "player_characters", ["player_id", "character_id"], [$playerId, $characterId]);
                    if (empty($characterData)) {
                        throw new CharacterException("Character was requested by user, but character ID was not found.");
                    }
                    break;

            }

        } catch (Exception $e) {
            throw new CharacterException("Error loading character: " . $e->getMessage());
        }

        // When character data has been retrieved...
        $this->dataContainer->loadFile(self::ATTACH_POINTS_FILE); // Load the file containing attachment points into memory.
        if (empty($characterData[0])) {
            if ($this->dataContainer->get('debug')) {
                echo PHP_EOL, "Debugging character data:", PHP_EOL;
                echo "Raw character data:", PHP_EOL;
                print_r($characterData); // ???????
                echo "First element of character data:", PHP_EOL;
                print_r($characterData[0]); // ???????
            }
            throw new CharacterException("Character data is empty after being successfully acquired?? wtf?? we should never get this, investigate data flow & DB integrity!!!");
        }
        $characterData = $characterData[0]; // Unwrap the character data.
        
        switch ($isLegacy) {

            case true:
                if ($characterId !== $characterData['legacy'] || $characterData['legacy'] !== 0) {
                    throw new CharacterException("Legacy character ID mismatch: requested {$characterId}, found {$characterData['legacy']}. (Shouldn't happen. HOW???)");
                }
                break;
            
            default:
                if ($characterId !== $characterData['character_id']) {
                    throw new CharacterException("Character ID mismatch: requested {$characterId}, found {$characterData['character_id']}. (HOW???)");
                }
        }

        try {
            $characterOptions = json_decode($characterData['character_options'], true, 512, JSON_THROW_ON_ERROR);
            $characterTitler = json_decode($characterData['character_titler'], true, 512, JSON_THROW_ON_ERROR);
            $characterStats = json_decode($characterData['character_stats'], true, 512, JSON_THROW_ON_ERROR);
            $compareArraysToTemplate = [
                $characterTitler,
                $characterOptions,
                $characterStats
            ];
            $characterTitler = $this->populateAndOrderFields($characterTitler, $this->defaultCharacterTitlerTemplate);
            $characterOptions = $this->populateAndOrderFields($characterOptions, $this->defaultCharacterOptionsTemplate);
            $characterStats = $this->populateAndOrderFields($characterStats, $this->defaultCharacterStatsTemplate);

            // If the keys don't exactly match in our arrays after populating, update as needed.
            // At this point even if we have a legacy character, we have their updated ID so we shouldn't
            // need to worry about it.
            if (array_keys($characterTitler) !== array_keys($this->defaultCharacterTitlerTemplate)) {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                }
                $this->update('character_data', ['character_titler'], [json_encode($characterTitler)], ['player_id', 'character_id'], [$playerId, $characterId]);
            }
            if (array_keys($characterOptions) !== array_keys($this->defaultCharacterOptionsTemplate)) {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                }
                $this->update('character_data', ['character_options'], [json_encode($characterOptions)], ['player_id', 'character_id'], [$playerId, $characterId]);
            }
            if (array_keys($characterStats) !== array_keys($this->defaultCharacterStatsTemplate)) {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                }
                $this->update('character_data', ['character_stats'], [json_encode($characterStats)], ['player_id', 'character_id'], [$playerId, $characterId]);
            }
            
            // Commit the transaction if we started one
            if ($db->inTransaction()) {
                $db->commitTransaction();
            }

            $attachmentPoint = &$characterOptions['attach_point']; // Reference to the attachment point; we WILL be intentionally replacing this for the client
            $attachmentPoint = strtolower($attachmentPoint);
            $attachmentPoints = $this->dataContainer->getFileData(self::ATTACH_POINTS_FILE);
            if (!isset($attachmentPoints[$attachmentPoint])) {
                throw new CharacterException("Attachment point '{$attachmentPoint}' is not defined.");
            }
            $attachmentPoint = $attachmentPoints[$attachmentPoint]; // Replace the attachment point with the actual attachment point integer.

            // Finally, condense both into a single array
            $characterData = [
                'character_id' => $characterId,
                'character_options' => $characterOptions,
                'character_titler' => $characterTitler
            ];
            // Make it JSON.
            $characterData = json_encode($characterData, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CharacterException("Failed to encode character data: " . $e->getMessage());
        }

        return $characterData; // Return the character data as a JSON string

    }

        /**
         * Populates missing fields in $data using $template, and reorders $data to match $template's key order.
         *
         * @param array $data     The array to populate and reorder.
         * @param array $template The template array to use for population and sorting.
         * @return array          The completed and ordered array.
         */
        private function populateAndOrderFields(array $data, array $template): array {
            // Explicitly check for 'template' key in both arrays and ensure they match
            if (!isset($data['template'])) {
                throw new CharacterException("Missing 'template' key in data array.");
            }
            if (!isset($template['template'])) {
                throw new CharacterException("Missing 'template' key in template array.");
            }
            if ($data['template'] !== $template['template']) {
                throw new CharacterException(
                    "Template mismatch: data array template is '" . $data['template'] . "', template array is '" . $template['template'] . "'."
                );
            }

            $templateFields = array_keys($template);
            // Remove 'template' from fields for population and ordering
            $fieldsNoTemplate = array_diff($templateFields, ['template']);
            $missingFields = array_diff($fieldsNoTemplate, array_keys($data));

            // Add missing fields
            foreach ($missingFields as $field) {
                $data[$field] = $template[$field];
            }

            // Remove any fields from $data that are not in the template
            foreach (array_keys($data) as $field) {
                if ($field !== 'template' && !in_array($field, $fieldsNoTemplate, true)) {
                    unset($data[$field]);
                }
            }

            $ordered = [];
            foreach ($fieldsNoTemplate as $field) {
                if (array_key_exists($field, $data)) {
                    $ordered[$field] = $data[$field];
                } else {
                    $ordered[$field] = $template[$field];
                }
            }
            // Always add 'template' as the last key
            $ordered['template'] = $template['template'];
            return $ordered;
        }

}
