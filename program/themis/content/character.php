<?php
declare(strict_types=1);
namespace Themis\Content;

require_once __DIR__. '/../Autoloader.php';

use Exception;
use Throwable;
use Themis\Character\Charactermancer;
use Themis\Utils\Exceptions\CharacterException;

class Character {
    private ?Charactermancer $charactermancer = null;
    private $character = null;

    public function __construct() {
        $this->charactermancer = new Charactermancer();
        $this->character = $this->getCharacterData();
    }

    private function getCharacterOverview(): string {
        $this->character = $_SESSION['character'] ?? null;
        if ($this->character === null) {
            return "No character selected.";
        }

        $titlerData = $this->character['character_titler'] ?? null;

        return "You are currently loaded in as " . htmlspecialchars($this->character['character_name'] ?? 'Unknown') . "." . PHP_EOL . print_r($titlerData, true);
    }

    private function getCharacterData(): array {
        $character = $_SESSION['character'] ?? null;
        if ($character === null) {
            return [];
        }
        return $character;
    }

    private function getCharacterTitler(): string {
        $titlerData = $this->character['character_titler'] ?? null;
        $out = '';
        
    // Form posts back to this endpoint with `updateTitler` so JS or server can detect and refresh
        $out .= '<form method="POST" action="" data-ajax="true" class="themis-character-form themis-titler-form">';
        $out .= '<input type="hidden" name="updateTitler" value="1">';
        $out .= '<textarea name="character_titler" class="themis-form-textarea" placeholder="Enter titler...">' . htmlspecialchars($titlerData) . '</textarea>';
        $out .= '<div class="themis-form-actions">';
        $out .= '<button type="submit" class="themis-form-button">Update Titler</button>';
        $out .= '</div>';
        $out .= '</form>';
        return $out;
    }

    public function render() {
        // Build and render the character page.

        $out = '';
        // Include our HUD-specific styles and behavior
        $out .= '<link rel="stylesheet" href="/themis/content/css/character_menu.css?v=1">';
        //$out .= '<script defer src="/themis/content/js/character_menu.js?v=2"></script>';

        // Wrapper that scopes all styles and scripts for the character HUD so
        // it does not interfere with the main global HUD interface.
        $out .= '<div class="themis-content-container themis-character-hud">';
        $out .= '<div class="themis-character-panel">';
        // Left menu (namespaced classes)
    $out .= '<nav class="themis-character-menu" aria-label="Character menu">';
    // Menu items call the HUD toggle helper for quick, HTML-side toggling when JS is available.
    $out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="overview" onclick="ThemisHUD.toggleVisibility(\'themis-character-content-block\', \'overview\'); return false;">Overview</a>';
    $out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="titler" onclick="ThemisHUD.toggleVisibility(\'themis-character-content-block\', \'titler\'); return false;">Titler</a>';
    $out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="inventory" onclick="ThemisHUD.toggleVisibility(\'themis-character-content-block\', \'inventory\'); return false;">Inventory</a>';
    $out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="skills" onclick="ThemisHUD.toggleVisibility(\'themis-character-content-block\', \'skills\'); return false;">Skills</a>';
    $out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="notes" onclick="ThemisHUD.toggleVisibility(\'themis-character-content-block\', \'notes\'); return false;">Notes</a>';
    $out .= '</nav>';

        // Right content area — filled by server-rendered blocks and toggled by JS
        $out .= '<section class="themis-character-content" id="themis-character-content">';
        $out .= '<div class="themis-character-content-block" data-key="overview">';
        $out .= '<center><h3>Overview</h3></center>';
        $out .= '<p>' . htmlspecialchars($this->getCharacterOverview()) . '</p>';
        $out .= '</div>';

        $out .= '<div class="themis-character-content-block" data-key="titler" hidden>'; 
        $out .= '<center><h3>Titler</h3></center>';
        $out .= '<p>' . $this->getCharacterTitler() . '</p>';
        $out .= '</div>';

        $out .= '<div class="themis-character-content-block" data-key="inventory" hidden>';
        $out .= '<h3>Inventory</h3>';
        $out .= '<p>Character inventory and equipped items.</p>';
        $out .= '</div>';

        $out .= '<div class="themis-character-content-block" data-key="skills" hidden>';
        $out .= '<h3>Skills</h3>';
        $out .= '<p>Track skill levels and progression here.</p>';
        $out .= '</div>';

        $out .= '<div class="themis-character-content-block" data-key="notes" hidden>';
        $out .= '<h3>Notes</h3>';
        $out .= '<p>Player or character notes, private to the character.</p>';
        $out .= '</div>';

        $out .= '</section>'; // .themis-character-content

        $out .= '</div>'; // .themis-character-panel
        $out .= '</div>'; // .themis-content-container.themis-character-hud

        echo $out;
    }
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Surrogate-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!session_start()) {
            throw new CharacterException('Failed to start session for character rendering');
        }
    }
    // The meat of the page.
    $character = new Character();

    // Do different things based on which request method is used.
    if ($_POST) {
        echo "You posted.", PHP_EOL, "Your posted data: ", PHP_EOL, print_r($_POST, true);
    } elseif ($_GET) {
    } else {
        // Default page.
        $character->render();
    }
    session_write_close();
} catch (Throwable $e) {
    session_write_close();
}

