-- Combat scaffold for turn-based PvP/NPC instances.
-- Designed for server-authoritative resolution with range-aware participation.

CREATE TABLE IF NOT EXISTS combat_instances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    host_player_uuid VARCHAR(36) NOT NULL,
    host_character_id INT NOT NULL,
    region_name VARCHAR(128) DEFAULT NULL,
    status ENUM('forming','active','completed','abandoned') NOT NULL DEFAULT 'forming',
    max_duelists INT NOT NULL DEFAULT 6,
    turn_seconds INT NOT NULL DEFAULT 90,
    last_round_no INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_combat_instances_status (status),
    KEY idx_combat_instances_host (host_player_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combat_instance_participants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    instance_id BIGINT UNSIGNED NOT NULL,
    entity_type ENUM('player','npc') NOT NULL DEFAULT 'player',
    player_uuid VARCHAR(36) DEFAULT NULL,
    character_id INT DEFAULT NULL,
    npc_id VARCHAR(64) DEFAULT NULL,
    display_name VARCHAR(255) NOT NULL,
    participant_state ENUM('invited','active','withdrawn','defeated','offline') NOT NULL DEFAULT 'active',
    is_host TINYINT(1) NOT NULL DEFAULT 0,
    current_hp INT NOT NULL DEFAULT 20,
    current_stamina INT NOT NULL DEFAULT 20,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_combat_participant_player (instance_id, player_uuid, entity_type),
    UNIQUE KEY uq_combat_participant_npc (instance_id, npc_id, entity_type),
    KEY idx_combat_participants_instance (instance_id),
    KEY idx_combat_participants_state (participant_state),
    CONSTRAINT fk_combat_participants_instance FOREIGN KEY (instance_id)
        REFERENCES combat_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combat_rounds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    instance_id BIGINT UNSIGNED NOT NULL,
    round_no INT NOT NULL,
    round_state ENUM('collecting','resolved') NOT NULL DEFAULT 'collecting',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deadline_at DATETIME NOT NULL,
    resolved_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_combat_round_instance_no (instance_id, round_no),
    KEY idx_combat_round_state_deadline (round_state, deadline_at),
    CONSTRAINT fk_combat_round_instance FOREIGN KEY (instance_id)
        REFERENCES combat_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combat_round_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    instance_id BIGINT UNSIGNED NOT NULL,
    round_id BIGINT UNSIGNED NOT NULL,
    actor_participant_id BIGINT UNSIGNED NOT NULL,
    target_participant_id BIGINT UNSIGNED DEFAULT NULL,
    action_type ENUM('attack','defend','feint','spell','wait','forfeit') NOT NULL DEFAULT 'wait',
    payload_json TEXT DEFAULT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolution_note VARCHAR(500) DEFAULT NULL,
    outcome_value INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_combat_action_actor_round (round_id, actor_participant_id),
    KEY idx_combat_actions_round (round_id),
    KEY idx_combat_actions_instance (instance_id),
    CONSTRAINT fk_combat_actions_round FOREIGN KEY (round_id)
        REFERENCES combat_rounds(id) ON DELETE CASCADE,
    CONSTRAINT fk_combat_actions_actor FOREIGN KEY (actor_participant_id)
        REFERENCES combat_instance_participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combat_presence (
    player_uuid VARCHAR(36) NOT NULL,
    character_id INT DEFAULT NULL,
    character_name VARCHAR(255) DEFAULT NULL,
    region_name VARCHAR(128) DEFAULT NULL,
    pos_x DECIMAL(8,2) DEFAULT NULL,
    pos_y DECIMAL(8,2) DEFAULT NULL,
    pos_z DECIMAL(8,2) DEFAULT NULL,
    nearby_json MEDIUMTEXT DEFAULT NULL,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (player_uuid),
    KEY idx_combat_presence_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combat_npcs (
    npc_id VARCHAR(64) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    template_json MEDIUMTEXT DEFAULT NULL,
    npc_state ENUM('active','inactive') NOT NULL DEFAULT 'active',
    home_region VARCHAR(128) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (npc_id),
    KEY idx_combat_npc_state (npc_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combat_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    instance_id BIGINT UNSIGNED NOT NULL,
    round_no INT NOT NULL DEFAULT 0,
    event_type VARCHAR(64) NOT NULL,
    event_json MEDIUMTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_combat_events_instance (instance_id),
    KEY idx_combat_events_created (created_at),
    CONSTRAINT fk_combat_events_instance FOREIGN KEY (instance_id)
        REFERENCES combat_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
