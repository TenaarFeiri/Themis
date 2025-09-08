<?php
declare(strict_types=1);
namespace Themis;

header('Content-Type: text/plain; charset=utf-8');

// Essential requires
require_once 'StrictErrorHandler.php'; // register strict handlers and themis_error_log
require_once 'Autoloader.php'; // Always second

// System imports
use Themis\System\DatabaseOperator;
use Themis\System\ThemisContainer;
use Themis\System\DataContainer;

// Exceptions
use PDOException;
use Exception;
use Throwable;
use Themis\Utils\Exceptions\AttacherException;
use Themis\Utils\Exceptions\BadRequestException;

class Attacher
{
    private bool $debug = true;
    private ?ThemisContainer $container = null;
    private ?DataContainer $dataContainer = null;
    private const ATTACH_POINTS_FILE = "secondlife_attachment_points.json";

    public function __construct() {
        $this->container = new ThemisContainer();
        $this->container->set(name: 'dataContainer', resolver: function () {
            return new DataContainer();
        });
        $this->container->set(name: 'dbOperator', resolver: function () {
            return new DatabaseOperator($this->container);
        });
        $this->dataContainer = $this->container->get(name: 'dataContainer'); // Get the DataContainer instance

        // The attacher only *gets* information from the database, and will not modify it.
        // It will return default information if nothing is found.
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            throw new AttacherException("Only GET requests are allowed.");
        }

        $this->dataContainer->set(key: 'debug', value: $this->debug);

        $cmd = $_GET['uuid'] ?? null;

        if ($cmd === null) {
            throw new BadRequestException("Missing 'uuid' parameter in GET request.");
        }

        $this->dataContainer->set(key: 'uuid', value: $cmd);
    }

    public function getAttachmentData(): array {
        $db = (object)$this->container->get(name: 'dbOperator');
        if (!$db) {
            throw new AttacherException("Database operator not found.");
        } elseif (!$db instanceof DatabaseOperator) {
            throw new AttacherException("Database operator is of incorrect type.");
        }
        $uuid = $this->dataContainer->get(key: 'uuid');

        $db->connectToDatabase();

        $playerInfo = $db->select(
            select: ["*"],
            from: "players",
            where: [
                "player_uuid"
            ],
            equals: [
                $uuid
            ]
        );

        $this->dataContainer->loadFile(fileName: self::ATTACH_POINTS_FILE);
        $defaultAttachmentPoint = $this->dataContainer->getFileData(filePathOrKey: self::ATTACH_POINTS_FILE)["head"];

        if ($playerInfo === false || !isset($playerInfo[0]) || $playerInfo[0]['player_current_character'] < 1) {
            // No player found or legacy import that's never been loaded before, that's fine. Return a default.
            return [
                "attach_point" => $defaultAttachmentPoint,
                "position" => "0,0,0"
            ];
        }

        $playerInfo = $playerInfo[0];

        // If this is successful we will have an array that contains in the 2nd level a JSON string.
        $characterInfo = $db->select(
            select: ["character_options"],
            from: "player_characters",
            where: [
                "character_id"
            ],
            equals: [
                $playerInfo['player_current_character']
            ]
        );

        // Expecting: [ 0 => [ 'character_options' => 'json string' ] ]
        if (!is_array($characterInfo) || !isset($characterInfo[0]['character_options'])) {
            themis_error_log(
                message: "Character id {$playerInfo['player_current_character']} not found or missing character_options for player: " . $playerInfo['player_uuid'] . ". Fell back to default attachment vars.",
                logname: 'attacher.log',
                debug: $this->debug
            );
            return [
                "attach_point" => $defaultAttachmentPoint,
                "position" => $playerInfo['position'] ?? "0,0,0"
            ];
        }

        $decoded = json_decode($characterInfo[0]['character_options'], true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            themis_error_log(
                message: "Failed to decode character_options JSON for character id {$playerInfo['player_current_character']} (player: {$playerInfo['player_uuid']}): " . json_last_error_msg(),
                logname: 'attacher.log',
                debug: $this->debug
            );
            return [
                "attach_point" => $defaultAttachmentPoint,
                "position" => $playerInfo['position'] ?? "0,0,0"
            ];
        }

        $attachKey = $decoded['attach_point'] ?? null;
        $attachmentPoints = $this->dataContainer->getFileData(filePathOrKey: self::ATTACH_POINTS_FILE);
        $characterAttachmentPoint = $attachKey !== null && isset($attachmentPoints[$attachKey]) ? $attachmentPoints[$attachKey] : null;

        return [
            "attach_point" => $characterAttachmentPoint ?? $defaultAttachmentPoint,
            "position" => $playerInfo['position'] ?? "0,0,0"
        ];
    }
}

setAutoloader();

try {
    ob_start();
    $attacher = new Attacher();
    echo json_encode($attacher->getAttachmentData());
    http_response_code(200);
    ob_end_flush();
} catch (AttacherException $e) {
    themis_error_log("Attacher error: " . $e->getMessage(), logname: 'attacher.log');
    http_response_code(500);
    ob_end_clean();
} catch (BadRequestException $e) {
    themis_error_log("Bad request error: " . $e->getMessage(), logname: 'attacher.log');
    http_response_code(400);
    ob_end_clean();
} catch (PDOException $e) {
    themis_error_log("Database error: " . $e->getMessage(), logname: 'attacher.log');
    http_response_code(500);
    ob_end_clean();
} 
// Catch these ones last.
catch (Exception $e) {
    themis_error_log("General error: " . $e->getMessage(), logname: 'attacher.log');
    http_response_code(500);
    ob_end_clean();
} catch (Throwable $t) {
    // Handle any other exceptions not caught otherwise.
    themis_error_log("Fatal error: " . $t->getMessage(), logname: 'attacher.log');
    http_response_code(500);
    ob_end_clean();
}

