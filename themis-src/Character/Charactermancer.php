<?php
declare(strict_types=1);
namespace Themis\Character;

/**
 * Charactermancer handles all the technical parts of characters. Creation, loading, updating, etc.
 * All objects that interact with a character in one way or another, will use this.
 */

// Character
use Themis\Character\CharacterRepository;

// System
use Themis\System\DatabaseOperator;
use Themis\System\DataContainer;

// Utils
use Themis\Utils\StringUtils;
use Themis\Utils\JsonUtils;

// Exception
use Exception;
use JsonException;
use Throwable;
use Themis\Utils\Exceptions\CharacterException;

class Charactermancer {

    private const DEFAULT_TEMPLATE = "default_character_format.json";
    private DataContainer $dataContainer;

    public function __construct() {
        $this->dataContainer = new DataContainer(); // Initialise a local datacontainer so we can fetch files.
    }

    public function getCharacterData(int $id): array {
        $characterRepository = new CharacterRepository();
        return $characterRepository->getCharacterData($id);
    }

    public function createCharacter(string $name): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new CharacterException("Session is not active. How??");
        }

        $name = StringUtils::trim(input: $name);

        $container = $this->dataContainer;
        $container->loadFile(fileName: self::DEFAULT_TEMPLATE, directory: "Defaults");
        $template = $container->getFileData(filePathOrKey: self::DEFAULT_TEMPLATE);
        if (!$template) {
            throw new CharacterException("Failed to load default character template");
        }

        $characterRepository = new CharacterRepository();
        $newCharacterId = $characterRepository->createNewCharacter($name, $template);
        if ($newCharacterId === false || $newCharacterId < 1) {
            throw new CharacterException("Failed to create new character");
        }

        return true;
    }

    public function loadCharacter(int $id): bool {
        // TODO: Implement character loading logic
        return false;
    }

}
