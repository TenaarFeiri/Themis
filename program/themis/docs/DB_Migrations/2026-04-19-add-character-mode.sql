-- Add authoritative titler mode to each character row.
-- Allowed values: normal, ooc, afk, combat.

ALTER TABLE player_characters
    ADD COLUMN character_mode ENUM('normal', 'ooc', 'afk', 'combat')
    NOT NULL DEFAULT 'normal'
    AFTER character_options;

CREATE INDEX idx_player_characters_mode ON player_characters (character_mode);
