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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Themis Duel Test Lab</title>
  <style>
    :root {
      --bg: #f4f7fb;
      --panel: #ffffff;
      --ink: #17324a;
      --muted: #4f6579;
      --line: #d6e0ea;
      --accent: #20639b;
      --accent-2: #2f855a;
      --danger: #b23a48;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Segoe UI", "Noto Sans", sans-serif;
      background: linear-gradient(160deg, #f8fbff 0%, #eef4fa 40%, #f3f7f2 100%);
      color: var(--ink);
    }
    .wrap {
      max-width: 1800px;
      margin: 0 auto;
      padding: 16px;
    }
    .panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 12px;
      box-shadow: 0 10px 24px rgba(23, 50, 74, 0.08);
    }
    h1 {
      margin: 0 0 10px;
      font-size: 1.25rem;
    }
    p {
      margin: 0 0 12px;
      color: var(--muted);
    }
    .controls {
      display: grid;
      grid-template-columns: repeat(2, minmax(220px, 1fr));
      gap: 10px;
      margin-bottom: 10px;
    }
    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    label {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--ink);
    }
    input {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 0.9rem;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 10px;
    }
    button {
      border: 0;
      border-radius: 8px;
      padding: 8px 12px;
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      background: var(--accent);
    }
    button.secondary {
      background: var(--accent-2);
    }
    button.warn {
      background: var(--danger);
    }
    pre {
      margin: 0;
      max-height: 160px;
      overflow: auto;
      background: #0d1b2a;
      color: #dbe9f5;
      border-radius: 8px;
      padding: 10px;
      font-size: 0.8rem;
    }
    .hud-grid {
      margin-top: 12px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      min-height: 72vh;
    }
    .hud-col {
      display: grid;
      grid-template-rows: auto 1fr;
      gap: 8px;
      min-height: 0;
    }
    .hud-title {
      font-weight: 700;
      font-size: 0.95rem;
      color: var(--ink);
    }
    iframe {
      width: 100%;
      height: 100%;
      min-height: 680px;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
    }
    @media (max-width: 1200px) {
      .controls {
        grid-template-columns: 1fr;
      }
      .hud-grid {
        grid-template-columns: 1fr;
      }
      iframe {
        min-height: 620px;
      }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="panel">
      <h1>Duel Test Lab</h1>
      <p>Run two independent combat test clients side-by-side with separate actor identities for local duel testing.</p>

      <div class="controls">
        <div class="field">
          <label for="left_uuid">Left HUD Player UUID</label>
          <input id="left_uuid" type="text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        </div>
        <div class="field">
          <label for="right_uuid">Right HUD Player UUID</label>
          <input id="right_uuid" type="text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        </div>
        <div class="field">
          <label for="left_name">Left Display Name</label>
          <input id="left_name" type="text" value="" placeholder="Leave empty for generated name">
        </div>
        <div class="field">
          <label for="right_name">Right Display Name</label>
          <input id="right_name" type="text" value="" placeholder="Leave empty for generated name">
        </div>
        <div class="field">
          <label for="region_name">Region Name</label>
          <input id="region_name" type="text" value="combat-test-lab">
        </div>
      </div>

      <div class="actions">
        <button type="button" class="secondary" id="create_fake_pair">Create Fake Pair</button>
        <button type="button" id="open_huds">Open Combat Clients</button>
        <button type="button" class="secondary" id="sync_pair">Prime Pair Radar</button>
        <button type="button" class="warn" id="tick">Run Resolver Tick</button>
      </div>

      <pre id="output">Create Fake Pair to auto-generate named duelists, then open both combat clients.</pre>
    </div>

    <div class="hud-grid">
      <div class="hud-col">
          <div class="hud-title">Left Combat Client</div>
        <iframe id="hud_left" loading="lazy" referrerpolicy="same-origin" src="about:blank"></iframe>
      </div>
      <div class="hud-col">
          <div class="hud-title">Right Combat Client</div>
        <iframe id="hud_right" loading="lazy" referrerpolicy="same-origin" src="about:blank"></iframe>
      </div>
    </div>
  </div>

  <script>
    (function(){
      var leftUuid = document.getElementById('left_uuid');
      var rightUuid = document.getElementById('right_uuid');
      var leftName = document.getElementById('left_name');
      var rightName = document.getElementById('right_name');
      var regionName = document.getElementById('region_name');
      var leftFrame = document.getElementById('hud_left');
      var rightFrame = document.getElementById('hud_right');
      var output = document.getElementById('output');

      function out(value){
        if(!output) return;
        if(typeof value === 'string') {
          output.textContent = value;
          return;
        }
        output.textContent = JSON.stringify(value, null, 2);
      }

      function validUuid(value){
        return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test((value || '').trim());
      }

      function values(){
        return {
          left_player_uuid: (leftUuid && leftUuid.value || '').trim(),
          right_player_uuid: (rightUuid && rightUuid.value || '').trim(),
          left_name: (leftName && leftName.value || 'Left Tester').trim(),
          right_name: (rightName && rightName.value || 'Right Tester').trim(),
          region_name: (regionName && regionName.value || 'combat-test-lab').trim()
        };
      }

      function mustUuids(){
        var v = values();
        if(!validUuid(v.left_player_uuid) || !validUuid(v.right_player_uuid)) {
          out('Both player UUID fields must be valid UUID values.');
          return null;
        }
        if(v.left_player_uuid.toLowerCase() === v.right_player_uuid.toLowerCase()) {
          out('Left and right UUID must be different.');
          return null;
        }
        return v;
      }

      function hudUrl(actorUuid, label, peerUuid){
        return '/themis/combat_test_client.php?test_mode=1&test_actor_uuid=' + encodeURIComponent(actorUuid) + '&peer_actor_uuid=' + encodeURIComponent(peerUuid || '') + '&label=' + encodeURIComponent(label || 'Combat Client');
      }

      function postForm(action, payload){
        var body = new URLSearchParams();
        var merged = Object.assign({ action: action, test_mode: '1' }, payload || {});
        Object.keys(merged).forEach(function(key){ body.set(key, merged[key]); });
        return fetch('/themis/combat_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString(),
          credentials: 'include'
        }).then(function(r){ return r.json(); });
      }

      document.getElementById('open_huds').addEventListener('click', function(){
        var v = mustUuids();
        if(!v) return;
        leftFrame.src = hudUrl(v.left_player_uuid, 'Left Combat Client', v.right_player_uuid);
        rightFrame.src = hudUrl(v.right_player_uuid, 'Right Combat Client', v.left_player_uuid);
        out('Dual combat clients loaded. Prime Pair Radar before challenge flow.');
      });

      document.getElementById('create_fake_pair').addEventListener('click', function(){
        var requested = values();
        postForm('test_create_fake_pair', {
          left_name: requested.left_name,
          right_name: requested.right_name
        })
          .then(function(data){
            if(!data || !data.ok || !data.left || !data.right) {
              out(data || 'Failed creating fake test pair.');
              return;
            }

            leftUuid.value = data.left.player_uuid || '';
            rightUuid.value = data.right.player_uuid || '';
            if(data.left.player_name) {
              leftName.value = data.left.player_name;
            }
            if(data.right.player_name) {
              rightName.value = data.right.player_name;
            }

            leftFrame.src = hudUrl(leftUuid.value, 'Left Combat Client', rightUuid.value);
            rightFrame.src = hudUrl(rightUuid.value, 'Right Combat Client', leftUuid.value);
            out(data);
          })
          .catch(function(err){ out(err.message || 'create fake pair error'); });
      });

      document.getElementById('sync_pair').addEventListener('click', function(){
        var v = mustUuids();
        if(!v) return;
        postForm('test_sync_pair', v)
          .then(function(data){ out(data); })
          .catch(function(err){ out(err.message || 'sync error'); });
      });

      document.getElementById('tick').addEventListener('click', function(){
        postForm('tick', {})
          .then(function(data){ out(data); })
          .catch(function(err){ out(err.message || 'tick error'); });
      });
    })();
  </script>
</body>
</html>
