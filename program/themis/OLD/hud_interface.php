<?php
// Development helper: send headers to prevent caching while working on assets.
// These must be sent before any output.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Surrogate-Control: no-store');

define('THEMIS_ACCESS', true); // Should prevent direct access to subsequent files imported to this HTML interface.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Themis HUD Interface</title>
  <link rel="stylesheet" href="/src/css/hud_interface.css?v=1.1">
</head>
<body>
  <div class="stage">
    <div class="hud" id="hud">
      <div class="menu-column">
        <div class="system-title">Themis RP System</div>
        <div class="menu-box">
          <div class="buttons-grid" id="buttonsGrid">
            <!-- 6 image buttons inserted by JS -->
          </div>
          <div class="arrow-row">
            <button id="prevPage" class="arrow">◀</button>
            <button id="nextPage" class="arrow">▶</button>
          </div>
          <div class="menu-note">
            <div style="padding:0.6vmax 0.8vmax;color:#333;font-size:0.95vmax"><?php echo "Notes / context / small status can appear here."; ?></div>
          </div>
        </div>
      </div>

      <div class="content-column">
        <div class="char-name" id="charName">Character Name</div>
        <div class="content-frame" id="contentFrame">
          <div style="padding:2%;font-size:1.2vmax;color:#333">Select a menu item to load content here.</div>
        </div>
      </div>

      <div class="controls">
        <button id="backBtn">&larr; Back</button>
        <button id="homeBtn">Home</button>
      </div>
    </div>
  </div>

  <script src="/src/js/hud_interface.js?v=1.1" defer></script>
</body>
</html>
