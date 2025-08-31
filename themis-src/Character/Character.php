<?php
declare(strict_types=1);
namespace Themis\Character;

use Themis\System\ThemisContainer;
use Themis\System\DataContainer;
use Themis\System\DatabaseOperator;


use Themis\Utils\StringUtils;

use Exception;
use JsonException;
use Themis\Utils\Exceptions\CharacterException;
use Themis\Utils\Exceptions\CharacterImportException;
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
        "color" => "255,255,255", // Text colour
        "opacity" => 1.0, // Fully opaque
        "attach_point" => "head", // Attachment point, head
        "position" => "0,0,0", // Offset from attachment point.
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
        $this->dbOperator = $container->get('databaseOperator');
    }

    public function run(): array {
        return [];
    }

    public function setImportState(bool $state): void {
        $this->isImporting = $state;
    }

    public function isImporting(): bool {
        return $this->isImporting;
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
        $db = $this->dbOperator;
        $titlerData = $this->defaultCharacterTitlerTemplate;
        $settingsData = $this->defaultCharacterSettingsTemplate;
        
        // Now loop through legacy characters to format and insert their data.
        // We should have received a multidimensional array of at least 2 levels (e.g [0][0]).
        // If not, bail.
        if (!is_array($legacyCharacters) || !isset($legacyCharacters[0]) || !is_array($legacyCharacters[0])) {
            throw new CharacterImportException("Invalid legacy character data format.");
        }
        $db->useConnection("default");
        $db->beginTransaction();
        foreach ($legacyCharacters as $data) {
            $parsedConstants = explode("=>", $data['constants']);
            $parsedTitler = explode("=>", $data['titles']);

            $characterArray = $titlerData; // Start with default titler data
            $characterArray['Name'] = $parsedTitler[0];
            $characterArray['Species'] = $parsedTitler[1];
            $characterArray['Mood'] = $parsedTitler[2];
            $characterArray['Status'] = $parsedTitler[3] . "\n" . $parsedTitler[4];
            $characterArray['Scent'] = $parsedTitler[5];
            $characterArray['Currently'] = $parsedTitler[6];
            $finalArray = [];

            $finalArray[$parsedConstants[0]] = $characterArray['Name'];
            $finalArray[$parsedConstants[1]] = $characterArray['Species'];
            $finalArray[$parsedConstants[2]] = $characterArray['Mood'];
            $finalArray[$parsedConstants[3]] = $characterArray['Status'];
            $finalArray[$parsedConstants[5]] = $characterArray['Scent'];
            $finalArray[$parsedConstants[6]] = $characterArray['Currently'];
            $finalArray["template"] = $characterArray['template'];

            $legacySettings = explode("=>", $data['settings']);
            $characterSettings = $settingsData;
            $characterSettings['color'] = $legacySettings[0];
            $characterSettings['opacity'] = $legacySettings[1];

            $this->createCharacter(array_values($finalArray)[0], $finalArray, $characterSettings, $characterSettings['attach_point'], (int)$data['character_id']);
        }

        $db->commitTransaction(); // Commit at the end of the import.
        return true;
    }

    private function createCharacter(string $name, array $titlerData = [], array $settingsData = [], string $attach_point = "head", int $legacy = 0): void {
        switch ($this->isImporting) {
            case false:
                if (!$this->dataContainer->has('cmd')) {
                    throw new CharacterException("Command data is not set in DataContainer.");
                }
                break;
        }
        $character = [];
        $settings = [];
        
        // I could've used a ternary here but I prefer the verbosity of the switch.
        switch ($titlerData) {
            case []:
                $character = $this->defaultCharacterTitlerTemplate;
                break;
            default:
                $character = $titlerData;
                break;
        }

        switch ($settingsData) {
            case []:
                $settings = $this->defaultCharacterSettingsTemplate;
                break;
            default:
                $settings = $settingsData;
                break;
        }

        $stats = $this->defaultCharacterStatsTemplate; // Stats are always default for new characters.

        if ($attach_point !== "head") {
            $settings['attach_point'] = $attach_point;
        }
        $character[0] = $name;
        try {
            $character = json_encode($character, JSON_THROW_ON_ERROR);
            $settings = json_encode($settings, JSON_THROW_ON_ERROR);
            $stats = json_encode($stats, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw $e;
        }

        $db = $this->dbOperator;
        if (!$db->inTransaction() && !$this->isImporting) {
            $db->beginTransaction();
        }
        $playerId = $this->dataContainer->get('userData')['player_id'];
        // insert(string $into, array $columns, array $values)
        $db->insert(
            "player_characters",
            // Rows 
            [   
                "player_id",
                "character_name", 
                "character_titler",
                "character_stats",
                "character_options",
                "legacy"
            ],
            // Values
            [
                $playerId,
                $name,
                $character,
                $stats,
                $settings,
                ($legacy < 0 ? 0 : $legacy)
            ]
        );

        if(!$this->isImporting) {
            // If we're importing a legacy character, we're probably doing it in bulk.
            // Only commit normal transactions immediately, the import method will commit when done.
            // Furthermore, if we're not importing then we had a manual new character creation, so
            // we will also call $this->loadCharacter($characterId); using the new character ID we just generated in this transaction
            $pdo = $this->dbOperator->getPdo();
            $characterId = (int)$pdo->lastInsertId();
            if ($characterId !== 0 && is_int($characterId)) {
                $this->loadCharacter($characterId);
            }
            $db->commitTransaction();
        }
    }

    private function findAttachPoint(string $attachPoint): int | false {
        if (!$this->dataContainer->has(self::ATTACH_POINTS_FILE)) {
            $this->dataContainer->loadFile(self::ATTACH_POINTS_FILE);
        }
        $attachmentPoints = $this->dataContainer->getFileData(self::ATTACH_POINTS_FILE);
        
        if (!isset($attachmentPoints[$attachPoint])) {
            if ($this->dataContainer->get('debug')) {
                echo "Attachment point '{$attachPoint}' not found in attachment points file.\n";
            }
            return false; // Attachment point not found
        } elseif (!is_int($attachmentPoints[$attachPoint])) {
            if ($this->dataContainer->get('debug')) {
                throw new Exception("Attachment point '{$attachPoint}' is not an integer in attachment points file.");
            }
            return false; // Attachment point is not an integer
        }
        return $attachmentPoints[$attachPoint];
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
            $characterOptions = $this->populateAndOrderFields($characterOptions, $this->defaultCharacterSettingsTemplate);
            $characterStats = $this->populateAndOrderFields($characterStats, $this->defaultCharacterStatsTemplate);

            // If the keys don't exactly match in our arrays after populating, update as needed.
            // At this point even if we have a legacy character, we have their updated ID so we shouldn't
            // need to worry about it.
            if (array_keys($characterTitler) !== array_keys($this->defaultCharacterTitlerTemplate)) {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                }
                $db->update('character_data', ['character_titler'], [json_encode($characterTitler)], ['player_id', 'character_id'], [$playerId, $characterId]);
            }
            if (array_keys($characterOptions) !== array_keys($this->defaultCharacterSettingsTemplate)) {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                }
                $db->update('character_data', ['character_options'], [json_encode($characterOptions)], ['player_id', 'character_id'], [$playerId, $characterId]);
            }
            if (array_keys($characterStats) !== array_keys($this->defaultCharacterStatsTemplate)) {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                }
                $db->update('character_data', ['character_stats'], [json_encode($characterStats)], ['player_id', 'character_id'], [$playerId, $characterId]);
            }
            
            // Commit the transaction if we started one
            if ($db->inTransaction()) {
                $db->commitTransaction();
            }

            $attachmentPoint = &$characterOptions['attach_point']; // Reference to the attachment point; we WILL be intentionally replacing this for the client
            $attachmentPoint = strtolower($attachmentPoint);
            $finalAttachmentPoint = $this->findAttachPoint($attachmentPoint); // False or integer
            switch ($finalAttachmentPoint) {
                case false: // Not found
                    throw new CharacterException("Attachment point '{$attachmentPoint}' not found in attachment points file.");
                default: // Found
                    $attachmentPoint = $finalAttachmentPoint;
            }

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
