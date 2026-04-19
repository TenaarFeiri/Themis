# Combat Scaffold (PvP + NPC-ready)

This is a server-authoritative turn-based combat scaffold with chat-range containment and instance pooling.

## Goals Covered

- Chat-range targeting from initiator radar data.
- Duel challenge creates/uses an instance with participant pool.
- Host-nearby join behavior for organic fight growth.
- Turn-based rounds with deadlines; no action => forfeit for round.
- Periodic range-drop cleanup when no active participants remain in mutual range.
- NPC-ready table and participant model.

## Main Components

- API endpoint: /themis/combat_api.php
- Service: themis-src/Combat/CombatService.php
- HUD panel: /themis/content/combat.php
- Menu entry: html/src/js/hud_interface.js

## Database

Migration:
- docs/DB_Migrations/2026-04-19-add-combat-scaffold.sql

Tables:
- combat_instances
- combat_instance_participants
- combat_rounds
- combat_round_actions
- combat_presence
- combat_npcs
- combat_events

## API Actions

All actions require authenticated HUD session.

1. POST action=radar_sync
- Payload (JSON preferred):
  - region_name
  - position: {x,y,z}
  - nearby: [{player_uuid, name}, ...]

2. GET action=targets
- Returns challenge targets resolved from nearby list + active character mapping.
- Includes joinable host instances if nearby.

3. POST action=challenge
- target_player_uuid
- turn_seconds (optional, default 90)

4. POST action=join
- host_player_uuid (optional; if omitted, service attempts any nearby host)

5. GET action=state
- instance_id (optional; defaults to caller active instance)

6. POST action=submit_action
- instance_id
- action_type: attack|defend|feint|spell|wait|forfeit
- target_player_uuid (optional)
- payload (optional JSON)
  - attack_kind: physical|magical
  - power: 0..12
  - lock_in: true (recommended)

7. GET or POST action=tick
- Resolves expired rounds and abandons instances with no mutual in-range participants.

8. POST action=test_sync_pair (test mode)
- Requires test_mode=1.
- Payload:
  - left_player_uuid
  - right_player_uuid
  - left_name (optional)
  - right_name (optional)
  - region_name (optional)
- Writes reciprocal nearby presence for both players to quickly stage a duel test.

## Dual HUD Test Mode

Use /themis/hud_duel_test.php while authenticated.

- Enter two different existing player UUIDs.
- Click Open Dual HUDs to launch side-by-side HUD clients.
- Click Prime Pair Radar once before target/challenge actions.
- In each HUD, open Combat and proceed as normal.

How it works:
- hud_interface.php forwards test_mode and test_actor_uuid query params to content fragments.
- combat.php forwards these values to combat_api.php.
- combat_api.php uses test_actor_uuid as the acting player in test mode.

## Realtime Sync Mode

- Combat test clients can connect to the Socket.IO gateway.
- When participants load/join an instance, they join room `combat:<instance_id>`.
- When any participant locks in an action, server resolves and pushes `combat:state_updated` to all room members.
- HTTP calls remain as fallback if socket is unavailable.

## Test Harness UI Enhancements

- Action selection is button-based (including Forfeit).
- Power slider is vertical for quick Sorcery-style commitments.
- Participant vitals (HP and Energy) are visible in instance view.
- Overview includes a human-readable combat log derived from events and round actions.

## Round/Resolution Rules (Scaffold)

- Round is collecting until all active participants submit or deadline passes.
- Round resolves immediately when all active participants have submitted locked-in actions.
- Missing submissions at deadline become forfeit actions.
- Offensive actions can carry attack_kind (physical|magical) and committed power (0-12).
- If defender matches both kind and power, damage is negated.
- Defend mitigates damage based on committed power.
- HP <= 0 transitions participant to defeated.
- If fewer than 2 active participants remain, instance completes.

## NPC Extension Path

- combat_npcs table is present.
- participant entity_type already supports player|npc.
- Next step is adding npc_id participant insertion and simple AI action generator during tick.

## Important Notes

- This is scaffold code intended for rapid iteration, not final combat balance.
- Use periodic radar_sync calls from SL/HUD for reliable range checks.
- Keep resolution server-authoritative; clients only submit intents.
