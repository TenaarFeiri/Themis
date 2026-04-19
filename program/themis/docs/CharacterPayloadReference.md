# Character Payload Reference (Sekhmet)

Source:
- Database: themis
- Table: player_characters
- Row: character_name = Sekhmet, character_id = 1

This file captures the current payload structure exactly as stored in the character BLOB JSON fields. It is the canonical reference for upcoming pipeline work.

## Raw Field Sizes (Bytes)

- character_titler: 186
- character_stats: 100
- character_options: 140

## Minified JSON Payload Sizes (Bytes)

- titler: 186
- stats: 100
- options: 140
- combined (titler + stats + options): 426

## Titler Format

```json
{
  "@invis@": "Sekhmet",
  "Daughter of Zerda\nSpecies:": "Lion.",
  "Mood:": "Good.",
  "Info:": "Babyless!\nHealthy!",
  "Scent:": "Leonine.",
  "Currently:": "Engaged.",
  "template": "character",
  "0": "Sekhmet"
}
```

Observed key/value type profile:
- @invis@: string
- Daughter of Zerda\nSpecies:: string
- Mood:: string
- Info:: string
- Scent:: string
- Currently:: string
- template: string
- 0: string

## Stats Format

```json
{
  "health": 1,
  "strength": 10,
  "dexterity": 10,
  "constitution": 10,
  "magic": 100,
  "class": 0,
  "template": "stats"
}
```

Observed key/value type profile:
- health: integer
- strength: integer
- dexterity: integer
- constitution: integer
- magic: integer
- class: integer
- template: string

## Options Format

```json
{
  "color": "255,255,0",
  "opacity": "1.0",
  "attach_point": "head",
  "position": "<0,0,0>",
  "afk-ooc": 0,
  "afk-msg": "",
  "ooc-msg": "",
  "template": "settings"
}
```

Observed key/value type profile:
- color: string
- opacity: string
- attach_point: string
- position: string
- afk-ooc: integer
- afk-msg: string
- ooc-msg: string
- template: string

## Notes

- The fields are stored as BLOBs but contain valid JSON strings.
- JSON decoding succeeded for all three payload fields.
- This reference should be treated as server-authoritative schema input for SL payload serialization work.
