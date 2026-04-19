# Titler API

Endpoint:
- /themis/titler_api.php

Authentication:
- Requires an authenticated HUD session established via hud_gate.php.
- Uses current session player and current loaded character.

## Actions

## GET payload

Request:
- /themis/titler_api.php?action=payload

Response:
- ok
- character_id
- payload (includes template/mode/layout/style/chatter)

## GET names (for chatter integration)

Request:
- /themis/titler_api.php?action=names

Response:
- ok
- character_id
- chatter:
  - primary_name
  - aliases
  - tokens

This is intended as the stable extraction contract for external chatter tooling.

## POST set_mode

Request:
- /themis/titler_api.php
- form fields:
  - action=set_mode
  - mode=normal|ooc|afk|combat
  - push=1|0 (optional, default 1)

Behavior:
1. Persists character mode server-side.
2. Rebuilds titler payload.
3. Optionally pushes chunked payload to SL titler URL.

Response:
- ok
- mode
- pushed
- payload

## POST or GET push

Request:
- /themis/titler_api.php?action=push

Behavior:
- Builds current authoritative titler payload and sends to SL titler endpoint.

Response:
- ok
- pushed

## Notes

- Payload pushes use chunk=true and payload_type=titler.
- Names data is also included in payload under payload.chatter.
- If character_mode migration is not applied yet, mode fallback is inferred from legacy character_options["afk-ooc"].
