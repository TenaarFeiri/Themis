<?php
declare(strict_types=1);

namespace Themis;

require_once __DIR__ . '/StrictErrorHandler.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!session_start()) {
        http_response_code(500);
        echo 'Failed to start session';
        exit;
    }
}

if (!isset($_SESSION['player']) || !is_array($_SESSION['player'])) {
    http_response_code(401);
    echo 'Not authenticated';
    exit;
}

$testMode = trim((string)($_GET['test_mode'] ?? '')) === '1' ? '1' : '0';
$testActorUuid = trim((string)($_GET['test_actor_uuid'] ?? ''));
$clientLabel = trim((string)($_GET['label'] ?? 'Combat Client'));

$realtimeConfig = [
  'enabled' => $testMode === '1' && $testActorUuid !== '',
  'url' => '',
  'path' => '/socket.io',
  'playerUuid' => $testActorUuid,
];

ob_start();
require __DIR__ . '/content/combat.php';
$combatFragment = (string)ob_get_clean();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Themis Combat Test Client</title>
  <script>
    window.ThemisRealtime = <?php echo json_encode($realtimeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    (function(){
      var cfg = window.ThemisRealtime || {};
      cfg.gatewayBase = cfg.url || (window.location.protocol + '//' + window.location.hostname + ':3101');
      window.ThemisRealtime = cfg;

      function loadScript(src, onLoad, onError){
        var s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.onload = onLoad;
        s.onerror = onError;
        document.head.appendChild(s);
      }

      loadScript('/socket.io/socket.io.js', function(){
        cfg.socketScriptSource = 'proxy';
      }, function(){
        loadScript(cfg.gatewayBase + '/socket.io/socket.io.js', function(){
          cfg.url = cfg.gatewayBase;
          cfg.socketScriptSource = 'direct';
        }, function(){
          cfg.enabled = false;
          cfg.socketScriptSource = 'missing';
        });
      });
    })();
  </script>
  <style>
    body {
      margin: 0;
      background: linear-gradient(180deg, #f5f6ef 0%, #ece8d8 100%);
      font-family: "Segoe UI", "Noto Sans", sans-serif;
      color: #2a2e2f;
    }
    .client-shell {
      min-height: 100vh;
      display: grid;
      grid-template-rows: auto 1fr;
    }
    .client-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.15);
      background: #d8d2bc;
      font-weight: 700;
      color: #2b2b2b;
    }
    .client-meta {
      font-size: 0.8rem;
      font-weight: 600;
      color: #48442f;
      word-break: break-all;
      text-align: right;
    }
    .client-content {
      padding: 10px;
      overflow: auto;
    }
  </style>
</head>
<body>
  <div class="client-shell">
    <div class="client-header">
      <div><?php echo htmlspecialchars($clientLabel, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="client-meta">
        test_mode=<?php echo htmlspecialchars($testMode, ENT_QUOTES, 'UTF-8'); ?><br>
        actor=<?php echo htmlspecialchars($testActorUuid !== '' ? $testActorUuid : 'none', ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
    <div class="client-content">
      <?php echo $combatFragment; ?>
    </div>
  </div>

  <script>
    // Minimal fragment helper expected by combat.php menu handlers.
    window.ThemisHUD = window.ThemisHUD || {};
    window.ThemisHUD.toggleVisibility = function(blockClass, activeKey){
      var blocks = document.querySelectorAll('.' + blockClass);
      blocks.forEach(function(el){
        var isActive = (el.getAttribute('data-key') || '') === activeKey;
        el.hidden = !isActive;
      });
    };
  </script>
</body>
</html>
