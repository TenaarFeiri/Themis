<?php
namespace Themis;
require_once __DIR__ . '/Autoloader.php';
use Exception;
if (headers_sent($f, $l)) {
    // handle/log: can't start session if headers already sent
    throw new Exception("Headers already sent in $f:$l");
}
try {
    if(session_status() !== PHP_SESSION_ACTIVE) {
        if (!session_start()) {
            throw new Exception('Failed to start session');
        }
    }
} catch (Exception $e) {
    // Handle session start error
    themis_error_log($e->getMessage());
    http_response_code(500);
    echo 'An error occurred during session initialization.';
    exit;
}
// Development helper: send headers to prevent caching while working on assets.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Surrogate-Control: no-store');

define('THEMIS_ACCESS', true);

$characterName = "Character Name";
?>
<?php 
/**
 * Controller class for interface functions like updating things like character names and getting
 * information about the player, or news or notes.
 */
use Themis\System\DatabaseOperator;
use Throwable;
use Themis\System\ThemisContainer;
use Themis\System\DataContainer;
class HudInterface {

  public static bool $debug = false;

  private $player;
  private ThemisContainer $container;
  private DataContainer $dataContainer;
  private DatabaseOperator $dbOperator;

  private const SYSTEM_CLASSES = [
      'dbOperator' => DatabaseOperator::class
  ];

  public function __construct() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      throw new Exception('Session not active in HudInterface constructor.');
    }
    if ($_SESSION === []) {
      throw new Exception('No session data found.');
    }
    if (!isset($_SESSION['player'])) {
      throw new Exception('No player session data found.');
    }
    if (!isset($_SESSION['player']['player_uuid'])) {
      throw new Exception('No player UUID found in session.');
    }
    $this->player = $_SESSION['player']; // Store player data

    $this->container = new ThemisContainer();
    $this->dataContainer = new DataContainer();
    $this->container->set('dataContainer', function () {
        return $this->dataContainer;
    });
    foreach (self::SYSTEM_CLASSES as $name => $class) {
        $this->container->set($name, function () use ($class) {
            return new $class($this->container);
        });
    }

    $this->dbOperator = $this->container->get('dbOperator');
  }

  // Get the current character's name on first load, if possible.
  public function getCurrentCharacterName(): string {
    // Fetch character name from session data
    $id = $this->player['player_id'];
    $currentCharacterId = $this->player['player_current_character'];
    $idColumn = ($currentCharacterId > 0) ? 'character_id' : 'legacy';
    if ($idColumn === 0) {
      return "No Character"; // New user, no character created (maybe we were early, it should've been done automatically)
    }
    $db = $this->dbOperator;

    $db->connectToDatabase();

    $character = $db->select(
      select: ["*"],
      from: "player_characters",
      where: ["player_id", $idColumn],
      equals: [$id, $currentCharacterId]
    );
    if (!$character) {
      return "Character not found";
    }
    $character = $character[0];
    $_SESSION['character'] = $character; // Store character data in session for later use

    return $character['character_name'];
  }

}

try {
  $hud = new HudInterface();
  $characterName = $hud->getCurrentCharacterName();
} catch (Throwable $e) {
  // Handle initialization errors
  themis_error_log($e->getMessage());
  http_response_code(500);
  echo 'An error occurred during HUD initialization.';
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Themis HUD Interface</title>
  <link rel="stylesheet" href="/src/css/hud_interface.css?v=2">
</head>
<body>
  <div class="stage">
    <div class="hud" id="hud">
      <div class="menu-column">
        <div class="system-title">Themis RP System</div>
        <div class="menu-box">
          <div class="buttons-grid" id="buttonsGrid">
            <!-- buttons inserted by JS -->
          </div>
          <div class="arrow-row">
            <button id="prevPage" class="arrow">◀</button>
            <button id="nextPage" class="arrow">▶</button>
          </div>
          <div class="menu-note">
            <div style="padding:6px 8px;color:#333"><?php echo "Notes / context / small status can appear here."; ?></div>
          </div>
        </div>
      </div>

      <div class="content-column">
        <div class="char-name" id="charName"><?php echo $characterName; ?></div>
        <div class="content-frame" id="contentFrame">
          <div style="padding:16px;color:#333">Select a menu item to load content here.</div>
        </div>
      </div>

      <div class="controls">
        <button id="homeBtn">Home</button>
      </div>
      <!-- Idle overlay: shown after inactivity, click to reactivate -->
      <div id="hudIdleOverlay" role="button" aria-hidden="true">
        <div class="msg">HUD is idle. Do anything to the HUD to reactivate. May require an extra click on SL media prims.</div>
      </div>
    </div>
  </div>

  <script src="/src/js/hud_interface.js?v=2" defer></script>
</body>
</html>
<?php session_write_close(); ?>