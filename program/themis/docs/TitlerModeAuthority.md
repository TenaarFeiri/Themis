# Titler Mode Authority (Server-Side)

Purpose:
- Reduce payload size and avoid client-side ambiguity by making mode and panel layout server-authoritative.

## Canonical Rule

- Server decides active titler mode and exact 4-panel render layout.
- SL titler object should render provided panels as-is (with 255-byte safety clipping only).

## Mode Values

- normal
- ooc
- afk
- combat

## DB Tracking

Character mode is persisted per loaded character row:
- table: player_characters
- column: character_mode

Migration file:
- docs/DB_Migrations/2026-04-19-add-character-mode.sql

## Runtime Flow

1. Character mode is read from player_characters.character_mode.
2. Server builds mode-specific titler body and splits into 4 panel strings.
3. Server sends payload as chunked `pt=titler` message.
4. SL titler reassembles chunks, ACKs each chunk, renders layout.panels.

API helpers:
- /themis/titler_api.php?action=payload
- /themis/titler_api.php?action=names
- POST /themis/titler_api.php (action=set_mode)
- /themis/titler_api.php?action=push

## Backward Compatibility

If `character_mode` column is not available yet:
- server can infer mode from legacy character_options["afk-ooc"]
- 0 => normal, 1 => ooc, 2 => afk

This fallback should be treated as transitional until migration is applied.

## Chatter Integration

Authoritative name extraction for external chatter tools is published from server payload:
- payload.chatter.primary_name
- payload.chatter.aliases
- payload.chatter.tokens

Use `/themis/titler_api.php?action=names` for a compact chatter-only response.
