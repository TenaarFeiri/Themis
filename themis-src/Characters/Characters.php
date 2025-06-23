<?php
	namespace Themis\Characters;
	/**
	* Controls every action related to characters, like getting information, updating,
	* creating new characters, etc.
	*/

    use Themis\Interface\CharactersInterface;
	use Themis\System\SystemDataStorage;
    use Themis\Database\DatabaseOperator;
    use Themis\Data\ArrayProcessor;
	use Themis\Data\StringProcessor;
	use Themis\Utilities\Assertion as Assert;
	use Exception;

	class Characters implements CharactersInterface
	{
		private ?object $database = null;
        private ?object $systemData = null;

        // Character default properties
        private int $statPointMax = 10;

		private const ERROR_WRONG_DB_CLASS = "database object is invalid or otherwise null, i.e not db operator";

		public function __construct(object $sysData)
        {
            $this->systemData = $sysData;
            if (!($this->systemData instanceof SystemDataStorage)) {
                throw new Exception("SystemDataStorage not included in Characters class.");
            }
			$this->database = new DatabaseOperator($this->systemData);
			if (!($this->database instanceof DatabaseOperator)) {
				// If we're not an instance of the operator, throw errors.
                throw new Exception(self::ERROR_WRONG_DB_CLASS);
            }
        }

        public function createNewCharacter()
        {
            // Create a new character with basic variables, then apply a name to it.
            // Stat allocation happens separately during the character creation process.
            if ($this->systemData->readData("requestData") === null) {
                // How did we even get here?? This shouldn't happen.
                if ($this->systemData->inDebugMode) {
                    // Var dump this nonsense if it ever happens, then throw an exception.
                    var_dump($this->systemData);
                }
                throw new Exception("Somehow we hit character creation without requestData. WTF?");
            }
            
            $defaultTemplate = [
                "name" => "My name",
                "tags" => [],
                "description" => "My description",
                "stats" => [
                    "Physical" => 10,
                    "Magical" => 0, // Populated in character creator.
                    "Elemental" => 0, // Populated in character creator.
                    "PhyDef" => 10,
                    "MagDef" => 10,
                    "Points" => $this->statPointMax
                ],
                "deleted" => 0
            ];

            $requestData = $this->systemData->readData("requestData");
            if (isset($requestData["name"]) && is_string($requestData["name"])) {
                $trimmedName = trim($requestData["name"]);
                if (!empty($trimmedName)) {
                    $defaultTemplate["name"] = $trimmedName;
                }
            }
            
            $arrayProcessor = new ArrayProcessor($this->systemData);
        }
	}

