<?php
declare(strict_types=1);
namespace Themis\User;


use Themis\System\DatabaseOperator;
use Themis\System\DataContainer;
use Themis\System\ThemisContainer;

use Themis\Character\Character;

use PDO;
use PDOException;
use Exception;

class UserLegacyImportException extends Exception {}
class UserLegacyImport {

    private ?ThemisContainer $container = null;
    private ?DataContainer $dataContainer = null;
    private ?DatabaseOperator $databaseOperator = null;
    private $debug = false;
    
    public function __construct(ThemisContainer $container) {
        $this->container = $container;
        $this->dataContainer = $this->container->get('dataContainer');
        $this->debug = $this->dataContainer->get('debug');
        // Clone the database operator to avoid modifying the original instance
        $this->databaseOperator = $this->container->get('databaseOperator');
        $dbo = $this->databaseOperator; // Alias for brevity
        try {
            $dbo->connectToDatabase("rp_tool"); // Attempt connection to legacy db
            if ($dbo->hasConnection("rp_tool") === false) {
                throw new Exception("Failed to connect to rp_tool database.");
            }
            // Error will be thrown if it fails.
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function importLegacyData(): bool {
        $dbo = $this->databaseOperator;
        $dataContainer = $this->dataContainer;
        $uuid = $dataContainer->get('slHeaders')["HTTP_X_SECONDLIFE_OWNER_KEY"];
        $legacyData = null;

        // Stage 1: Lookup user in legacy database.
        try {
            // First things first: Swap to the rp_tool connection!
            $dbo->useConnection("rp_tool");
            $legacyData = $dbo->manualQuery("SELECT * FROM `rp_tool`.`users` WHERE `uuid` = ?", [(string)$uuid]);
            if ($this->debug) {
                print_r($legacyData);
            }
            if (empty($legacyData)) {
                return false; // False means no users found. All is right in the world.
            } elseif (count($legacyData) > 1) {
                throw new UserLegacyImportException("Multiple users found with the same UUID: {$uuid}");
            } else {
                $legacyData = $legacyData[0]; // Kick the md-array up a step.
                if ($this->debug) {
                    echo PHP_EOL, "Single-dimensional array: ", PHP_EOL;
                    print_r($legacyData);
                }
            }
        } catch (PDOException $e) {
            throw new UserLegacyImportException($e->getMessage());
        }

        // Stage 2: Check for character data in legacy database.
        $characterData = null;
        $legacyUserId = $legacyData["id"];
        try {
            $characterData = $dbo->manualQuery("SELECT * FROM `rp_tool`.`rp_tool_character_repository` WHERE `user_id` = ?", [(string)$legacyUserId]);
            if ($this->debug) {
                echo PHP_EOL, PHP_EOL, "Character data: ", PHP_EOL;
                print_r($characterData);
            }
            if (empty($characterData)) {
                return false; // No characters to import, all is well.
            }
        } catch (PDOException $e) {
            throw new UserLegacyImportException($e->getMessage());
        }

        // Stage 3: Import character data into new database.
        $character = new Character($this->container);
        $this->dataContainer->set('importing', true);
        if (!$this->dataContainer->has('cmd')) {
            $this->dataContainer->set('cmd', []);
        }
        $character->setImportState(true); // Uncomment to enable importing mode
        $import = $character->importLegacyCharacters($characterData);
        if (!$import) {
            return false; // Nothing to import; errors would've thrown otherwise.
        }
        
        // Stage 4: Set the user's last loaded legacy character as its current one.
        $lastCharacterId = "-{$legacyData['lastchar']}"; // Prefix with a dash to indicate legacy status
        $dbo->useConnection("default");
        $dbo->beginTransaction();
        try {
            $update = $dbo->update(
                "players",
                ["player_current_character"],
                [(string)$lastCharacterId],
                ["player_uuid"],
                [(string)$uuid]
            );
            if ($update === false) {
                $dbo->rollbackTransaction();
                throw new UserLegacyImportException("Failed to update user's current character.");
            }
            $dbo->commitTransaction();
        } catch (PDOException $e) {
            $dbo->rollbackTransaction();
            throw new UserLegacyImportException($e->getMessage());
        }

        return true; // Success!
    }
}
