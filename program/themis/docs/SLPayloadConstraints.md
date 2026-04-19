# SL Payload Constraints and Pipeline Notes

Context:
- In non-dev mode, server responses are only sent to SL objects that ping the server.
- The practical data cap is approximately 2048 bytes per message, with hard truncation behavior on the SL side.

## Hard Constraint

- Target maximum payload size per outbound message: 2048 bytes.
- Recommended operational ceiling: 1800 bytes to leave room for envelope overhead, separators, metadata, and future protocol growth.

## Current Character Payload Footprint (Sekhmet Reference)

- titler minified: 186 bytes
- stats minified: 100 bytes
- options minified: 140 bytes
- combined: 426 bytes

This baseline is safe as a single payload today, but future fields, localization, and richer metadata can easily exceed budget.

## Design Direction (Server Authoritative)

- Server is source of truth for character state and render-ready payloads.
- Client/SL object should receive only what it explicitly asked for.
- Prefer field-scoped responses over monolithic full-state packets.

## Recommended Transport Strategy

1. Message typing:
- Use strict message types: titler, stats, options, delta, ack, error.

2. Chunking protocol:
- If payload > budget, split into ordered chunks with headers:
- msg_id, chunk_index, chunk_total, payload_type, crc/hash.
- Require per-chunk ACK responses from SL object before sending next chunk.
- See `SLChunkingProtocol.md` for canonical envelope and ACK formats.

3. Delta mode:
- Send only changed keys after initial full sync.
- Cache last acknowledged state fingerprint per object.

4. Key compaction:
- Use short wire keys for transport only (server map both ways).
- Keep canonical long keys in DB and internal API.

5. Optional compression layer:
- Use lightweight dictionary substitutions for repeated strings before transmit.
- Avoid CPU-heavy compression if it risks script timeouts.

6. Integrity and replay safety:
- Include nonce/timestamp and HMAC or signature for critical state changes.
- Reject stale or malformed chunk sets.

## Draft Wire Envelope (Example)

```json
{
  "t": "options",
  "id": "a1b2",
  "i": 1,
  "n": 1,
  "p": {"ap":"head","pos":"<0,0,0>"}
}
```

Where:
- t: payload type
- id: message id
- i: chunk index (1-based)
- n: chunk count
- p: payload body

## Next Implementation Milestones

1. Add server-side payload serializer with byte-budget estimator.
2. Add chunker and reassembler contracts in docs and code.
3. Add per-object sync state table (last hash, last acked seq, last seen).
4. Add non-dev enforcement: no unsolicited push, only ping-response.
