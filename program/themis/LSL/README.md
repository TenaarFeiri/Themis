# Themis LSL Frameworks

This folder contains starter scripts for two SL objects that consume Themis chunked server payloads.

Files:
- Themis_Titler_Framework.lsl
- Themis_HUD_Framework.lsl

## Protocol Compatibility

Both scripts implement the server contract from:
- html/themis/docs/SLChunkingProtocol.md

Supported inbound envelope:
- t=chunk
- id=<message id>
- pt=<payload type>
- i=<1-based chunk index>
- n=<total chunks>
- ack=1
- enc=b64
- d=<base64 data>

Supported ACK response body:

```json
{"t":"ack","id":"<id>","i":<index>,"ok":1}
```

## Titler Framework

Purpose:
- Reassembles chunked payloads and renders a single logical text body over 4 prim text panels.
- Uses byte-aware splitting so each panel stays within 255-byte SetText constraints.

Behavior:
- TITLER_LINKS defaults to [2,3,4,5].
- Server-authoritative path: if payload includes layout.panels, those panel strings are rendered directly.
- Legacy fallback path: text is formatted from titler payload JSON and distributed sequentially by UTF-8 bytes.
- Overflow is marked with [...].

Inputs:
- pt=titler or pt=generic: display text update.
- pt=options: updates text color/opacity when color and opacity keys are present.

Mode model:
- Mode is authoritative on server and expected in titler payload as mode=normal|ooc|afk|combat.
- Character mode should be persisted in player_characters.character_mode.
- SL should render what server sends; mode inference in LSL is not source of truth.

Chatter integration model:
- Server payload includes chatter block with primary_name, aliases, and tokens.
- External tooling can fetch names from /themis/titler_api.php?action=names.

## HUD Framework

Purpose:
- Reassembles chunked payloads and updates media prim URL/settings for WebHUD.

Behavior:
- Defaults to current prim and face 0.
- Applies web URL from webhud_url or url.
- Can accept link, face, auto_play, loop in payload.

Inputs:
- pt=hud, pt=options, or pt=generic.

## Deployment Notes

1. Put the correct script in the intended object.
2. Reset script and capture printed HTTP-in URL from owner chat.
3. Store that URL server-side in player_titler_url or player_hud_url.
4. Send with chunk=true on server so ACK workflow is enforced.

## Server Option Mapping

Use these SrvToSLComms options:
- chunk=true
- payload_type=titler | options | hud | generic
- mode=titler | hud
- budget=1800 (or lower if desired)

## Important Limitations

- LSL scripts are single-threaded; heavy formatting should stay compact.
- Script state is in-memory only; object/script reset clears partial chunk data.
- These are frameworks/starters, not final product UI logic.
