<?php
declare(strict_types=1);

namespace Themis\Content;

require_once __DIR__ . '/../StrictErrorHandler.php';
require_once __DIR__ . '/../Autoloader.php';

use Exception;
use Themis\System\DatabaseOperator;

/**
 * Character controller for making new characters and loading existing ones.
 */

use Themis\Utils\Exceptions\CharacterException;
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!session_start()) {
        throw new CharacterException('Failed to start session for load rendering');
    }
}

class Characters {
    private ?DatabaseOperator $db = null;

    public function __construct() {
        // DatabaseOperator may be used later; for now we store small demo data in session
        $this->db = new DatabaseOperator();
    }

    private function getOverview(): string {
        return 'Manage player characters: create a new character by name, or load the list.';
    }

    private function getNewCharacterForm(): string {
        $out = '';
        $out .= '<form method="POST" action="" data-ajax="true" class="themis-character-form themis-newchar-form">';
        $out .= '<input type="hidden" name="createCharacter" value="1">';
        $out .= '<label for="new_character_name">Character name</label>';
        $out .= '<input id="new_character_name" name="new_character_name" type="text" class="themis-form-input" placeholder="Enter character name">';
        $out .= '<div class="themis-form-actions">';
        $out .= '<button type="submit" class="themis-form-button">Create Character</button>';
        $out .= '</div>';
        $out .= '</form>';
        return $out;
    }

    private function getListPlaceholder(): string {
        $out = '';
        $out .= '<div id="characters-list-placeholder">';
        $out .= '<p>Character list will be loaded here. <a href="#" onclick="ThemisHUD.loadContent(\'/themis/content/characters_list.php\', \'Characters\'); return false;">Load list now</a></p>';
        $out .= '<div id="characters-list" aria-live="polite"></div>';
        $out .= '</div>';
        return $out;
    }

    public function render(): string {
        $out = '';
        $out .= '<link rel="stylesheet" href="/themis/content/css/character_menu.css?v=1">';

        $out .= '<div class="themis-content-container themis-character-hud">';
        $out .= '<div class="themis-character-panel">';

        // Left menu
        // Developer note: non-main fragment menus (profile, characters, etc.)
        // should keep carved backgrounds and borders confined to the menu
        // column. Use the helper class `themis-fragment-menu` on the menu
        // container to ensure align-self:flex-start; height:auto; and
        // overflow:visible so the menu sizes to its items and hover raises
        // render outside the box without stretching under the content pane.
        $out .= '<nav class="themis-character-menu themis-fragment-menu" aria-label="Characters menu">';
        $out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="overview" onclick="ThemisHUD.toggleVisibility(\'themis-character-content-block\', \'overview\'); return false;">Overview</a>';
        $out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="new" onclick="ThemisHUD.toggleVisibility(\'themis-character-content-block\', \'new\'); return false;">New Character</a>';
        $out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="list" onclick="ThemisHUD.toggleVisibility(\'themis-character-content-block\', \'list\'); return false;">List</a>';
        $out .= '</nav>';

        // Right content
        $out .= '<section class="themis-character-content" id="themis-character-content">';

        $out .= '<div class="themis-character-content-block" data-key="overview">';
        $out .= '<h3>Overview</h3>';
        $out .= '<p>' . htmlspecialchars($this->getOverview()) . '</p>';
        $out .= '</div>';

        $out .= '<div class="themis-character-content-block" data-key="new" hidden>'; 
        $out .= '<h3>New Character</h3>';
        $out .= $this->getNewCharacterForm();
        $out .= '</div>';

        $out .= '<div class="themis-character-content-block" data-key="list" hidden>';
        $out .= '<h3>Characters</h3>';
        $out .= $this->getListPlaceholder();
        $out .= '</div>';

        $out .= '</section>'; // .themis-character-content

        $out .= '</div>'; // .themis-character-panel
        $out .= '</div>'; // .themis-content-container.themis-character-hud

        return $out;
    }
}

// Server-side handling: allow simple createCharacter posts to be stored in session for demo
try{
    if(!isset($_SESSION)) session_start();
    if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['createCharacter'])){
        $name = trim((string)($_POST['new_character_name'] ?? ''));
        if($name !== ''){
            if(!isset($_SESSION['characters']) || !is_array($_SESSION['characters'])) $_SESSION['characters'] = [];
            $_SESSION['characters'][] = $name;
        }
    }
}catch(Exception $_e){}

// Render fragment
$chars = new Characters();
echo $chars->render();

session_write_close();
