# SL Chunking Protocol (Server -> Object)

This document defines the wire contract when payloads exceed the configured operational budget and are chunked by the server.

## When Chunking Is Used

- Chunking is enabled per send by passing chunk=true in server options.
- With chunk=true, the server always sends chunk envelopes (even when payload fits in one chunk).
- If payload exceeds budget, the server emits multiple ordered chunks; otherwise it emits one chunk.
- Each chunk requires an ACK response before the next chunk is sent.

## Chunk Envelope (JSON)

```json
{
  "t": "chunk",
  "id": "a1b2c3d4e5f6",
  "pt": "options",
  "i": 1,
  "n": 3,
  "ack": 1,
  "enc": "b64",
  "d": "eyJmb28iOiJiYXIifQ=="
}
```

Field meanings:
- t: message type, always chunk
- id: message id shared by all chunks in a logical payload
- pt: payload type (titler, stats, options, generic, etc.)
- i: current chunk index (1-based)
- n: total number of chunks
- ack: always 1, indicates ACK is required
- enc: chunk data encoding, currently b64
- d: base64 chunk data

## ACK Response Expectations

The SL object must return an HTTP 200 body with one of the accepted ACK formats.

Preferred JSON ACK:

```json
{
  "t": "ack",
  "id": "a1b2c3d4e5f6",
  "i": 1,
  "ok": 1
}
```

Accepted plain-text ACK fallback:

```text
ACK a1b2c3d4e5f6 1
```

ACK validation rules:
- id must match the chunk message id.
- i must match the exact chunk index received.
- ok must be truthy for JSON ACK (true, 1, "1", "true").

If ACK is missing or invalid, transmission fails and stops.

## Server-Authoritative Titler Layout

For titler payloads (`pt=titler`), server should send a render-ready layout payload so object scripts do not infer mode or line assignment locally.

Example reassembled payload body:

```json
{
  "template": "titler_layout_v1",
  "mode": "combat",
  "layout": {
    "panels": [
      "Sekhmet",
      "[COMBAT]\nHP: 1",
      "STR: 10  DEX: 10",
      "CON: 10  MAG: 100"
    ],
    "panel_count": 4,
    "max_panel_bytes": 255
  },
  "style": {
    "color": "255,255,0",
    "opacity": "1.0"
  },
  "chatter": {
    "primary_name": "Sekhmet",
    "aliases": ["Sekhmet"],
    "tokens": ["sekhmet"]
  }
}
```

Mode values:
- normal
- ooc
- afk
- combat

SL titler script behavior:
- Prefer `layout.panels` directly.
- Treat local formatting/splitting only as fallback for legacy payloads.
- Keep hard safety clipping at 255 bytes per panel.

## SL Reassembly Expectations

- Group chunks by id.
- Ensure chunk indexes 1..n are present exactly once.
- Sort by i, concatenate decoded d values in order.
- Base64 decode each d and append raw bytes/string segments.
- Parse the reconstructed JSON payload only after all chunks are received and validated.

## Failure Behavior

Server aborts chunk stream on first failure:
- non-200 HTTP response
- transport error
- missing or invalid ACK

Object should discard partial chunk sets after timeout or protocol mismatch.

## Notes

- This protocol is server authoritative and request/response driven.
- Non-dev mode should only emit data in response to object ping/request flows.
- Keep each envelope under configured operational budget and always below hard cap.
