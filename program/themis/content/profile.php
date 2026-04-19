<?php
declare(strict_types=1);
namespace Themis\Content;

require_once __DIR__. '/../Autoloader.php';

use Exception;
use Throwable;
use Themis\Character\Charactermancer;


class ProfileException extends Exception {}
class Profile {
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
        $mode = strtolower((string)($this->character['character_mode'] ?? 'normal'));
        if (!in_array($mode, ['normal', 'ooc', 'afk', 'combat'], true)) {
            $mode = 'normal';
        }
        $out = '';

    $out .= '<form method="POST" action="/themis/titler_api.php" class="themis-profile-form themis-titler-mode-form">';
        $out .= '<input type="hidden" name="action" value="set_mode">';
        $out .= '<label for="themis_titler_mode">Titler Mode</label>';
        $out .= '<select id="themis_titler_mode" name="mode" class="themis-form-input">';
        $out .= '<option value="normal"' . ($mode === 'normal' ? ' selected' : '') . '>Normal</option>';
        $out .= '<option value="ooc"' . ($mode === 'ooc' ? ' selected' : '') . '>OOC</option>';
        $out .= '<option value="afk"' . ($mode === 'afk' ? ' selected' : '') . '>AFK</option>';
        $out .= '<option value="combat"' . ($mode === 'combat' ? ' selected' : '') . '>Combat</option>';
        $out .= '</select>';
        $out .= '<div class="themis-profile-actions">';
        $out .= '<button type="submit" class="themis-profile-button">Apply Mode + Push</button>';
        $out .= '<button type="button" class="themis-profile-button" id="themis_mode_save_only">Save Mode Only</button>';
        $out .= '<button type="button" class="themis-profile-button" id="themis_mode_push_current">Push Current Payload</button>';
        $out .= '</div>';
        $out .= '<div id="themis_titler_mode_status" aria-live="polite" style="margin-top:8px; font-size:0.9em;"></div>';
        $out .= '</form>';

        $out .= '<hr style="margin: 14px 0;">';
        $out .= '<div class="themis-profile-form">';
        $out .= '<label for="themis_chatter_names">Chatter Names Export</label>';
        $out .= '<textarea id="themis_chatter_names" class="themis-profile-textarea" rows="5" readonly placeholder="Click refresh to extract chatter names from authoritative server payload."></textarea>';
        $out .= '<div class="themis-profile-actions">';
        $out .= '<button type="button" class="themis-profile-button" id="themis_chatter_refresh">Refresh Names</button>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<hr style="margin: 14px 0;">';
        
    // Form posts back to this endpoint with `updateTitler` so JS or server can detect and refresh
    $out .= '<form method="POST" action="" data-ajax="true" class="themis-profile-form themis-titler-form">';
        $out .= '<input type="hidden" name="updateTitler" value="1">';
    $out .= '<textarea name="character_titler" class="themis-profile-textarea" placeholder="Enter titler...">' . htmlspecialchars($titlerData) . '</textarea>';
    $out .= '<div class="themis-profile-actions">';
    $out .= '<button type="submit" class="themis-profile-button">Update Titler</button>';
        $out .= '</div>';
        $out .= '</form>';
        return $out;
    }

    public function render() {
        // Build and render the character page.

        $out = '';
        // Include our HUD-specific styles and behavior
        $out .= '<link rel="stylesheet" href="/themis/content/css/profile_menu.css?v=1">';

            // Wrapper that scopes all styles and scripts for the character HUD so
            // it does not interfere with the main global HUD interface.
        $out .= '<div class="themis-content-container themis-profile-hud">';
        $out .= '<div class="themis-profile-panel">';
            // Left menu (namespaced classes)
    $out .= '<nav class="themis-profile-menu themis-fragment-menu" aria-label="Profile menu">';
        // Menu items call the HUD toggle helper for quick, HTML-side toggling when JS is available.
        $out .= '<a href="#" role="button" class="themis-profile-menu-item" data-key="overview" onclick="ThemisHUD.toggleVisibility(\'themis-profile-content-block\', \'overview\'); return false;">Overview</a>';
        $out .= '<a href="#" role="button" class="themis-profile-menu-item" data-key="titler" onclick="ThemisHUD.toggleVisibility(\'themis-profile-content-block\', \'titler\'); return false;">Titler</a>';
        $out .= '<a href="#" role="button" class="themis-profile-menu-item" data-key="inventory" onclick="ThemisHUD.toggleVisibility(\'themis-profile-content-block\', \'inventory\'); return false;">Inventory</a>';
        $out .= '<a href="#" role="button" class="themis-profile-menu-item" data-key="skills" onclick="ThemisHUD.toggleVisibility(\'themis-profile-content-block\', \'skills\'); return false;">Skills</a>';
        $out .= '<a href="#" role="button" class="themis-profile-menu-item" data-key="notes" onclick="ThemisHUD.toggleVisibility(\'themis-profile-content-block\', \'notes\'); return false;">Notes</a>';
        $out .= '</nav>';

            // Right content area — filled by server-rendered blocks and toggled by JS
        $out .= '<section class="themis-profile-content" id="themis-profile-content">';
        $out .= '<div class="themis-profile-content-block" data-key="overview">';
            $out .= '<center><h3>Overview</h3></center>';
            $out .= '<p>' . htmlspecialchars($this->getCharacterOverview()) . '</p>';
            $out .= '</div>';

        $out .= '<div class="themis-profile-content-block" data-key="titler" hidden>'; 
            $out .= '<center><h3>Titler</h3></center>';
            $out .= '<p>' . $this->getCharacterTitler() . '</p>';
            $out .= '</div>';

        $out .= '<div class="themis-profile-content-block" data-key="inventory" hidden>';
            $out .= '<h3>Inventory</h3>';
            $out .= '<p>Character inventory and equipped items.</p>';
            $out .= '</div>';

        $out .= '<div class="themis-profile-content-block" data-key="skills" hidden>';
            $out .= '<h3>Skills</h3>';
            $out .= '<p>Track skill levels and progression here.</p>';
            $out .= '</div>';

        $out .= '<div class="themis-profile-content-block" data-key="notes" hidden>';
            $out .= '<h3>Notes</h3>';
            $out .= '<p>Player or character notes, private to the character.</p>';
            $out .= '</div>';

            $out .= '</section>'; // .themis-character-content

            $out .= '<script>';
            $out .= '(function(){';
            $out .= 'var modeForm=document.querySelector(".themis-titler-mode-form");';
            $out .= 'if(!modeForm||modeForm.dataset.bound==="1"){return;}';
            $out .= 'modeForm.dataset.bound="1";';
            $out .= 'var modeSelect=document.getElementById("themis_titler_mode");';
            $out .= 'var statusEl=document.getElementById("themis_titler_mode_status");';
            $out .= 'var namesEl=document.getElementById("themis_chatter_names");';
            $out .= 'var saveOnlyBtn=document.getElementById("themis_mode_save_only");';
            $out .= 'var pushBtn=document.getElementById("themis_mode_push_current");';
            $out .= 'var refreshNamesBtn=document.getElementById("themis_chatter_refresh");';

            $out .= 'function setStatus(msg,isError){if(!statusEl){return;}statusEl.textContent=msg;statusEl.style.color=isError?"#b22222":"#1f4f1f";}';

            $out .= 'function postMode(pushValue){';
            $out .= 'if(!modeSelect){return;}';
            $out .= 'var body=new URLSearchParams();';
            $out .= 'body.set("action","set_mode");';
            $out .= 'body.set("mode",modeSelect.value);';
            $out .= 'body.set("push",pushValue?"1":"0");';
            $out .= 'setStatus("Updating mode...",false);';
            $out .= 'fetch("/themis/titler_api.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:body.toString()})';
            $out .= '.then(function(r){return r.json();})';
            $out .= '.then(function(data){if(!data||data.ok!==true){throw new Error((data&&data.error)||"Mode update failed");}setStatus("Mode set to "+data.mode+". Push="+(data.pushed?"yes":"no"),false);})';
            $out .= '.catch(function(err){setStatus(err.message||"Mode update failed",true);});';
            $out .= '}';

            $out .= 'function pushCurrent(){';
            $out .= 'setStatus("Pushing current payload...",false);';
            $out .= 'fetch("/themis/titler_api.php?action=push",{method:"POST"})';
            $out .= '.then(function(r){return r.json();})';
            $out .= '.then(function(data){if(!data||data.ok!==true){throw new Error((data&&data.error)||"Push failed");}setStatus("Payload pushed to titler endpoint.",false);})';
            $out .= '.catch(function(err){setStatus(err.message||"Push failed",true);});';
            $out .= '}';

            $out .= 'function refreshNames(){';
            $out .= 'if(!namesEl){return;}';
            $out .= 'namesEl.value="Loading...";';
            $out .= 'fetch("/themis/titler_api.php?action=names",{method:"GET"})';
            $out .= '.then(function(r){return r.json();})';
            $out .= '.then(function(data){if(!data||data.ok!==true){throw new Error((data&&data.error)||"Name export failed");}namesEl.value=JSON.stringify(data.chatter||{},null,2);setStatus("Chatter names refreshed.",false);})';
            $out .= '.catch(function(err){namesEl.value="";setStatus(err.message||"Name export failed",true);});';
            $out .= '}';

            $out .= 'modeForm.addEventListener("submit",function(e){e.preventDefault();postMode(true);});';
            $out .= 'if(saveOnlyBtn){saveOnlyBtn.addEventListener("click",function(){postMode(false);});}';
            $out .= 'if(pushBtn){pushBtn.addEventListener("click",pushCurrent);}';
            $out .= 'if(refreshNamesBtn){refreshNamesBtn.addEventListener("click",refreshNames);}';
            $out .= '})();';
            $out .= '</script>';

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
            throw new ProfileException('Failed to start session for profile rendering');
        }
    }
    // The meat of the page.
    $profile = new Profile();

    // Do different things based on which request method is used.
    if ($_POST) {
        echo "You posted.", PHP_EOL, "Your posted data: ", PHP_EOL, print_r($_POST, true);
    } elseif ($_GET) {
    } else {
        // Default page.
        $profile->render();
    }
    session_write_close();
} catch (Throwable $e) {
    session_write_close();
}

