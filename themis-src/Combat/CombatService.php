<?php
declare(strict_types=1);

namespace Themis\Combat;

use DateTimeImmutable;
use Throwable;
use Themis\System\DatabaseOperator;

final class CombatService
{
    private const PRESENCE_STALE_SECONDS = 240;
    private const DEFAULT_TURN_SECONDS = 90;
    private const MAX_HP = 20;
    private const MAX_STAMINA = 12;
    private const MAX_POWER = 12;
    private const WAIT_RECOVERY = 1;
    private const DEFEND_RECOVERY = 3;

    public function __construct(private readonly DatabaseOperator $db)
    {
        $this->db->connectToDatabase();
    }

    /**
     * @param array<string,mixed> $radarPayload
     * @return array<string,mixed>
     */
    public function syncPresence(string $playerUuid, array $radarPayload): array
    {
        $character = $this->getCurrentCharacterForPlayer($playerUuid);
        if ($character === null) {
            return ['ok' => false, 'error' => 'No active character'];
        }

        $region = (string)($radarPayload['region_name'] ?? '');
        $position = $radarPayload['position'] ?? [];
        $nearby = $radarPayload['nearby'] ?? [];
        if (!is_array($nearby)) {
            $nearby = [];
        }

        $normalizedNearby = [];
        foreach ($nearby as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $uuid = trim((string)($entry['player_uuid'] ?? $entry['uuid'] ?? ''));
            $name = trim((string)($entry['name'] ?? $entry['character_name'] ?? ''));
            if ($uuid === '' && $name === '') {
                continue;
            }
            $normalizedNearby[] = [
                'player_uuid' => $uuid,
                'name' => $name,
            ];
        }

        $now = $this->now();
        $exists = $this->db->select(['player_uuid'], 'combat_presence', ['player_uuid'], [$playerUuid]);
        $values = [
            $character['character_id'],
            $character['character_name'],
            $region !== '' ? $region : null,
            $this->floatOrNull($position['x'] ?? null),
            $this->floatOrNull($position['y'] ?? null),
            $this->floatOrNull($position['z'] ?? null),
            json_encode($normalizedNearby, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $now,
        ];

        if (count($exists) > 0) {
            $this->db->update(
                'combat_presence',
                ['character_id', 'character_name', 'region_name', 'pos_x', 'pos_y', 'pos_z', 'nearby_json', 'last_seen_at'],
                $values,
                ['player_uuid'],
                [$playerUuid]
            );
        } else {
            $this->db->insert(
                'combat_presence',
                ['player_uuid', 'character_id', 'character_name', 'region_name', 'pos_x', 'pos_y', 'pos_z', 'nearby_json', 'last_seen_at'],
                array_merge([$playerUuid], $values)
            );
        }

        return ['ok' => true, 'synced_at' => $now, 'nearby_count' => count($normalizedNearby)];
    }

    /**
     * @return array<string,mixed>
     */
    public function listTargets(string $playerUuid): array
    {
        $presence = $this->getPresence($playerUuid);
        if ($presence === null) {
            return ['ok' => true, 'targets' => [], 'joinable_instances' => []];
        }

        $nearby = $this->decodeNearby($presence['nearby_json'] ?? null);
        $targetUuids = [];
        foreach ($nearby as $entry) {
            $uuid = trim((string)($entry['player_uuid'] ?? ''));
            if ($uuid !== '' && $uuid !== $playerUuid) {
                $targetUuids[$uuid] = true;
            }
        }

        $targets = [];
        foreach (array_keys($targetUuids) as $uuid) {
            $targetCharacter = $this->getCurrentCharacterForPlayer($uuid);
            if ($targetCharacter === null) {
                continue;
            }

            $targets[] = [
                'player_uuid' => $uuid,
                'character_id' => (int)$targetCharacter['character_id'],
                'character_name' => (string)$targetCharacter['character_name'],
            ];
        }

        $joinable = $this->findJoinableInstances($playerUuid, array_keys($targetUuids));

        return ['ok' => true, 'targets' => $targets, 'joinable_instances' => $joinable];
    }

    /**
     * @return array<string,mixed>
     */
    public function challenge(string $hostPlayerUuid, string $targetPlayerUuid, int $turnSeconds = self::DEFAULT_TURN_SECONDS): array
    {
        if (!$this->isPlayerNearby($hostPlayerUuid, $targetPlayerUuid)) {
            return ['ok' => false, 'error' => 'Target is not in chat range according to latest radar sync'];
        }

        $hostCharacter = $this->getCurrentCharacterForPlayer($hostPlayerUuid);
        $targetCharacter = $this->getCurrentCharacterForPlayer($targetPlayerUuid);
        if ($hostCharacter === null || $targetCharacter === null) {
            return ['ok' => false, 'error' => 'Host or target has no active character'];
        }

        $existing = $this->findExistingSharedInstance($hostPlayerUuid, $targetPlayerUuid);
        if ($existing !== null) {
            return ['ok' => true, 'instance_id' => (int)$existing['id'], 'reused' => true];
        }

        $now = $this->now();
        $instanceId = $this->db->insert(
            'combat_instances',
            ['host_player_uuid', 'host_character_id', 'status', 'turn_seconds', 'created_at', 'updated_at', 'last_activity_at'],
            [$hostPlayerUuid, (int)$hostCharacter['character_id'], 'forming', max(20, $turnSeconds), $now, $now, $now]
        );

        $this->addPlayerParticipant((int)$instanceId, $hostPlayerUuid, $hostCharacter, true, 'active');
        $this->addPlayerParticipant((int)$instanceId, $targetPlayerUuid, $targetCharacter, false, 'invited');

        $this->logEvent((int)$instanceId, 0, 'challenge_created', [
            'host_player_uuid' => $hostPlayerUuid,
            'target_player_uuid' => $targetPlayerUuid,
        ]);

        return ['ok' => true, 'instance_id' => (int)$instanceId, 'reused' => false, 'invite_required' => true];
    }

    /**
     * @return array<string,mixed>
     */
    public function listPendingInvites(string $playerUuid): array
    {
        $sql = <<<'SQL'
SELECT ci.id AS instance_id, ci.host_player_uuid, ci.turn_seconds, ci.created_at, host.display_name AS host_display_name
FROM combat_instance_participants me
JOIN combat_instances ci ON ci.id = me.instance_id
LEFT JOIN combat_instance_participants host ON host.instance_id = ci.id AND host.is_host = 1
WHERE me.player_uuid = ?
  AND me.participant_state = 'invited'
  AND ci.status IN ('forming','active')
ORDER BY ci.id DESC
SQL;
        $rows = $this->db->manualQuery($sql, [$playerUuid]);
        return ['ok' => true, 'invites' => is_array($rows) ? $rows : []];
    }

    /**
     * @return array<string,mixed>
     */
    public function respondToInvite(string $playerUuid, int $instanceId, bool $accept): array
    {
        $participant = $this->getParticipantByPlayer($instanceId, $playerUuid);
        if ($participant === null) {
            return ['ok' => false, 'error' => 'Not part of this instance'];
        }
        if ((string)$participant['participant_state'] !== 'invited') {
            return ['ok' => false, 'error' => 'No pending invite for this instance'];
        }

        if ($accept) {
            $this->db->update(
                'combat_instance_participants',
                ['participant_state', 'last_seen_at'],
                ['active', $this->now()],
                ['id'],
                [(int)$participant['id']]
            );
            $this->logEvent($instanceId, 0, 'invite_accepted', ['player_uuid' => $playerUuid]);
            $this->ensureRoundCollecting($instanceId);
            return ['ok' => true, 'instance_id' => $instanceId, 'accepted' => true];
        }

        $this->db->update(
            'combat_instance_participants',
            ['participant_state', 'last_seen_at'],
            ['withdrawn', $this->now()],
            ['id'],
            [(int)$participant['id']]
        );
        $this->logEvent($instanceId, 0, 'invite_declined', ['player_uuid' => $playerUuid]);

        $active = $this->db->select(
            ['id'],
            'combat_instance_participants',
            ['instance_id', 'participant_state'],
            [$instanceId, 'active']
        );
        $invited = $this->db->select(
            ['id'],
            'combat_instance_participants',
            ['instance_id', 'participant_state'],
            [$instanceId, 'invited']
        );
        if (count($active) < 2 && count($invited) === 0) {
            $this->db->update('combat_instances', ['status', 'last_activity_at'], ['abandoned', $this->now()], ['id'], [$instanceId]);
        }

        return ['ok' => true, 'instance_id' => $instanceId, 'accepted' => false];
    }

    /**
     * @return array<string,mixed>
     */
    public function joinNearbyHost(string $playerUuid, ?string $hostPlayerUuid = null): array
    {
        $character = $this->getCurrentCharacterForPlayer($playerUuid);
        if ($character === null) {
            return ['ok' => false, 'error' => 'No active character'];
        }

        $presence = $this->getPresence($playerUuid);
        $nearby = $presence ? $this->decodeNearby($presence['nearby_json'] ?? null) : [];
        $nearbyUuids = [];
        foreach ($nearby as $entry) {
            $uuid = trim((string)($entry['player_uuid'] ?? ''));
            if ($uuid !== '') {
                $nearbyUuids[$uuid] = true;
            }
        }

        if ($hostPlayerUuid !== null && $hostPlayerUuid !== '' && !isset($nearbyUuids[$hostPlayerUuid])) {
            return ['ok' => false, 'error' => 'Host is not nearby'];
        }

        $candidateHosts = $hostPlayerUuid !== null && $hostPlayerUuid !== ''
            ? [$hostPlayerUuid]
            : array_keys($nearbyUuids);

        foreach ($candidateHosts as $hostUuid) {
            $instance = $this->findHostOpenInstance($hostUuid);
            if ($instance === null) {
                continue;
            }

            $instanceId = (int)$instance['id'];
            if (!$this->participantExists($instanceId, $playerUuid)) {
                $this->addPlayerParticipant($instanceId, $playerUuid, $character, false);
                $this->logEvent($instanceId, (int)$instance['last_round_no'], 'participant_joined', [
                    'player_uuid' => $playerUuid,
                    'host_player_uuid' => $hostUuid,
                ]);
            }

            $this->ensureRoundCollecting($instanceId);
            return ['ok' => true, 'instance_id' => $instanceId, 'host_player_uuid' => $hostUuid];
        }

        return ['ok' => false, 'error' => 'No nearby host instance found'];
    }

    /**
     * @return array<string,mixed>
     */
    public function submitAction(string $playerUuid, int $instanceId, string $actionType, ?string $targetPlayerUuid, array $payload = []): array
    {
        $participant = $this->getParticipantByPlayer($instanceId, $playerUuid);
        if ($participant === null) {
            return ['ok' => false, 'error' => 'Not part of this instance'];
        }
        if ((string)$participant['participant_state'] !== 'active') {
            return ['ok' => false, 'error' => 'Participant is not active'];
        }

        $round = $this->getOpenRound($instanceId);
        if ($round === null) {
            $round = $this->ensureRoundCollecting($instanceId);
            if ($round === null) {
                return ['ok' => false, 'error' => 'No collecting round available'];
            }
        }

        $allowedActions = ['attack', 'defend', 'feint', 'spell', 'wait', 'forfeit'];
        if (!in_array($actionType, $allowedActions, true)) {
            $actionType = 'wait';
        }

        $targetParticipantId = null;
        if ($targetPlayerUuid !== null && $targetPlayerUuid !== '') {
            $target = $this->getParticipantByPlayer($instanceId, $targetPlayerUuid);
            if ($target !== null && (string)$target['participant_state'] === 'active') {
                $targetParticipantId = (int)$target['id'];
            }
        }

        if ($targetParticipantId === null && in_array($actionType, ['attack', 'spell', 'feint'], true)) {
            $targetParticipantId = $this->pickDefaultTargetParticipantId($instanceId, (int)$participant['id']);
        }

        $exists = $this->db->select(
            ['id'],
            'combat_round_actions',
            ['round_id', 'actor_participant_id'],
            [(int)$round['id'], (int)$participant['id']]
        );

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Power is constrained by available stamina at submission time.
        if (in_array($actionType, ['attack', 'spell', 'feint'], true)) {
            $requestedPower = $this->resolvePower($payload);
            $availableStamina = max(0, min(self::MAX_STAMINA, (int)($participant['current_stamina'] ?? self::MAX_STAMINA)));
            $payload['power'] = min($requestedPower, $availableStamina, self::MAX_POWER);
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($actionType === 'defend') {
            // Defend should always regenerate and not consume stamina.
            $payload['power'] = max(0, min(self::MAX_POWER, $this->resolvePower($payload)));
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (count($exists) > 0) {
            $this->db->update(
                'combat_round_actions',
                ['target_participant_id', 'action_type', 'payload_json', 'submitted_at'],
                [$targetParticipantId, $actionType, $payloadJson, $this->now()],
                ['id'],
                [(int)$exists[0]['id']]
            );
        } else {
            $this->db->insert(
                'combat_round_actions',
                ['instance_id', 'round_id', 'actor_participant_id', 'target_participant_id', 'action_type', 'payload_json', 'submitted_at'],
                [$instanceId, (int)$round['id'], (int)$participant['id'], $targetParticipantId, $actionType, $payloadJson, $this->now()]
            );
        }

        $this->touchInstance($instanceId);
        $this->maybeResolveRound($instanceId);

        return ['ok' => true, 'instance_id' => $instanceId, 'round_no' => (int)$round['round_no']];
    }

    /**
     * @return array<string,mixed>
     */
    public function getState(string $playerUuid, ?int $instanceId = null): array
    {
        if ($instanceId === null) {
            $instanceId = $this->findActiveInstanceForPlayer($playerUuid);
            if ($instanceId === null) {
                return ['ok' => true, 'instance' => null];
            }
        }

        $instances = $this->db->select(['*'], 'combat_instances', ['id'], [$instanceId]);
        if (count($instances) === 0) {
            return ['ok' => true, 'instance' => null];
        }
        $instance = $instances[0];

        $participants = $this->db->select(
            ['id', 'player_uuid', 'character_id', 'display_name', 'participant_state', 'is_host', 'current_hp', 'current_stamina', 'last_seen_at'],
            'combat_instance_participants',
            ['instance_id'],
            [$instanceId]
        );

        $openRound = $this->getOpenRound($instanceId);
        $roundData = null;
        if ($openRound !== null) {
            $actions = $this->db->select(
                ['actor_participant_id', 'target_participant_id', 'action_type', 'payload_json', 'submitted_at', 'resolution_note', 'outcome_value'],
                'combat_round_actions',
                ['round_id'],
                [(int)$openRound['id']]
            );

            $roundData = [
                'id' => (int)$openRound['id'],
                'round_no' => (int)$openRound['round_no'],
                'state' => (string)$openRound['round_state'],
                'started_at' => (string)$openRound['started_at'],
                'deadline_at' => (string)$openRound['deadline_at'],
                'actions' => $actions,
            ];
        }

        $eventsSql = <<<'SQL'
SELECT round_no, event_type, event_json, created_at
FROM combat_events
WHERE instance_id = ?
ORDER BY id DESC
LIMIT 25
SQL;
        $eventsRows = $this->db->manualQuery($eventsSql, [$instanceId]);
        $events = is_array($eventsRows) ? array_reverse($eventsRows) : [];

        return [
            'ok' => true,
            'instance' => [
                'id' => (int)$instance['id'],
                'status' => (string)$instance['status'],
                'host_player_uuid' => (string)$instance['host_player_uuid'],
                'turn_seconds' => (int)$instance['turn_seconds'],
                'last_round_no' => (int)$instance['last_round_no'],
                'last_activity_at' => (string)$instance['last_activity_at'],
                'rules' => [
                    'max_hp' => self::MAX_HP,
                    'max_stamina' => self::MAX_STAMINA,
                    'max_power' => self::MAX_POWER,
                    'defend_recovery' => self::DEFEND_RECOVERY,
                    'wait_recovery' => self::WAIT_RECOVERY,
                ],
                'participants' => $participants,
                'round' => $roundData,
                'events' => $events,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function tick(): array
    {
        $resolvedInstances = 0;
        $closedInstances = 0;

        $openInstances = $this->db->select(['id', 'status'], 'combat_instances', ['status'], [['forming', 'active']]);
        foreach ($openInstances as $instance) {
            $instanceId = (int)$instance['id'];
            $resolved = $this->maybeResolveRound($instanceId);
            if ($resolved) {
                $resolvedInstances++;
            }
            if ($this->shouldCloseForRangeDrop($instanceId)) {
                $this->db->update('combat_instances', ['status', 'last_activity_at'], ['abandoned', $this->now()], ['id'], [$instanceId]);
                $this->logEvent($instanceId, 0, 'instance_abandoned_range', ['reason' => 'no participants in mutual range']);
                $closedInstances++;
            }
        }

        return ['ok' => true, 'resolved_instances' => $resolvedInstances, 'closed_instances' => $closedInstances];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getCurrentCharacterForPlayer(string $playerUuid): ?array
    {
        $sql = <<<'SQL'
SELECT p.player_uuid, p.player_current_character, c.character_id, c.character_name
FROM players p
LEFT JOIN player_characters c ON c.character_id = p.player_current_character
WHERE p.player_uuid = ?
LIMIT 1
SQL;
        $rows = $this->db->manualQuery($sql, [$playerUuid]);
        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }
        $row = $rows[0];
        if (!isset($row['character_id']) || (int)$row['character_id'] <= 0) {
            return null;
        }
        return $row;
    }

    private function addPlayerParticipant(int $instanceId, string $playerUuid, array $character, bool $isHost, string $participantState = 'active'): void
    {
        $participantState = in_array($participantState, ['invited', 'active', 'withdrawn', 'defeated', 'offline'], true)
            ? $participantState
            : 'active';

        if ($this->participantExists($instanceId, $playerUuid)) {
            $this->db->update(
                'combat_instance_participants',
                ['participant_state', 'last_seen_at'],
                [$participantState, $this->now()],
                ['instance_id', 'player_uuid'],
                [$instanceId, $playerUuid]
            );
            return;
        }

        $this->db->insert(
            'combat_instance_participants',
            ['instance_id', 'entity_type', 'player_uuid', 'character_id', 'display_name', 'participant_state', 'is_host', 'current_hp', 'current_stamina', 'joined_at', 'last_seen_at'],
            [
                $instanceId,
                'player',
                $playerUuid,
                (int)$character['character_id'],
                (string)$character['character_name'],
                $participantState,
                $isHost ? 1 : 0,
                self::MAX_HP,
                self::MAX_STAMINA,
                $this->now(),
                $this->now(),
            ]
        );

        if ($isHost) {
            $this->db->update('combat_instances', ['host_player_uuid', 'host_character_id'], [$playerUuid, (int)$character['character_id']], ['id'], [$instanceId]);
        }
    }

    private function participantExists(int $instanceId, string $playerUuid): bool
    {
        $rows = $this->db->select(['id'], 'combat_instance_participants', ['instance_id', 'player_uuid'], [$instanceId, $playerUuid]);
        return count($rows) > 0;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getParticipantByPlayer(int $instanceId, string $playerUuid): ?array
    {
        $rows = $this->db->select(['*'], 'combat_instance_participants', ['instance_id', 'player_uuid'], [$instanceId, $playerUuid]);
        return $rows[0] ?? null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function ensureRoundCollecting(int $instanceId): ?array
    {
        $open = $this->getOpenRound($instanceId);
        if ($open !== null) {
            return $open;
        }

        $active = $this->db->select(
            ['id'],
            'combat_instance_participants',
            ['instance_id', 'participant_state'],
            [$instanceId, 'active']
        );

        if (count($active) < 2) {
            return null;
        }

        $instanceRows = $this->db->select(['turn_seconds', 'last_round_no', 'status'], 'combat_instances', ['id'], [$instanceId]);
        if (count($instanceRows) === 0) {
            return null;
        }
        $instance = $instanceRows[0];

        $nextRound = ((int)$instance['last_round_no']) + 1;
        $turnSeconds = max(20, (int)$instance['turn_seconds']);
        $nowDt = new DateTimeImmutable('now');
        $deadline = $nowDt->modify('+' . $turnSeconds . ' seconds');

        $this->db->insert(
            'combat_rounds',
            ['instance_id', 'round_no', 'round_state', 'started_at', 'deadline_at'],
            [$instanceId, $nextRound, 'collecting', $nowDt->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s')]
        );

        $instanceStatus = $instance['status'] === 'forming' ? 'active' : $instance['status'];
        $this->db->update(
            'combat_instances',
            ['status', 'last_round_no', 'last_activity_at'],
            [$instanceStatus, $nextRound, $this->now()],
            ['id'],
            [$instanceId]
        );

        $this->logEvent($instanceId, $nextRound, 'round_started', ['turn_seconds' => $turnSeconds]);

        return $this->getOpenRound($instanceId);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getOpenRound(int $instanceId): ?array
    {
        $rows = $this->db->select(
            ['*'],
            'combat_rounds',
            ['instance_id', 'round_state'],
            [$instanceId, 'collecting'],
            ordered: true,
            orderedBy: 'round_no',
            ascending: false
        );

        return $rows[0] ?? null;
    }

    private function maybeResolveRound(int $instanceId): bool
    {
        $round = $this->getOpenRound($instanceId);
        if ($round === null) {
            return false;
        }

        $participants = $this->db->select(
            ['id', 'participant_state', 'current_hp', 'current_stamina'],
            'combat_instance_participants',
            ['instance_id', 'participant_state'],
            [$instanceId, 'active']
        );
        if (count($participants) < 2) {
            $this->db->update('combat_instances', ['status'], ['completed'], ['id'], [$instanceId]);
            return false;
        }

        $actions = $this->db->select(
            ['*'],
            'combat_round_actions',
            ['round_id'],
            [(int)$round['id']]
        );

        $submittedBy = [];
        foreach ($actions as $action) {
            $submittedBy[(int)$action['actor_participant_id']] = true;
        }

        $deadlinePassed = (new DateTimeImmutable('now')) >= new DateTimeImmutable((string)$round['deadline_at']);
        if (!$deadlinePassed && count($submittedBy) < count($participants)) {
            return false;
        }

        if ($deadlinePassed) {
            foreach ($participants as $p) {
                $pid = (int)$p['id'];
                if (!isset($submittedBy[$pid])) {
                    $this->db->insert(
                        'combat_round_actions',
                        ['instance_id', 'round_id', 'actor_participant_id', 'action_type', 'submitted_at', 'resolution_note'],
                        [$instanceId, (int)$round['id'], $pid, 'forfeit', $this->now(), 'Timed out and forfeited this round']
                    );
                }
            }
            $actions = $this->db->select(['*'], 'combat_round_actions', ['round_id'], [(int)$round['id']]);
        }

        $actionsByActor = [];
        foreach ($actions as $action) {
            $actionsByActor[(int)$action['actor_participant_id']] = $action;
        }

        $participantById = [];
        foreach ($participants as $p) {
            $participantById[(int)$p['id']] = $p;
        }

        $staminaDeltaByParticipant = [];

        foreach ($actions as $action) {
            $type = (string)$action['action_type'];
            $actorId = (int)$action['actor_participant_id'];
            $targetId = isset($action['target_participant_id']) ? (int)$action['target_participant_id'] : 0;
            $payload = $this->decodePayload($action['payload_json'] ?? null);
            $attackKind = $this->resolveAttackKind($type, $payload);
            $actorStamina = max(0, min(self::MAX_STAMINA, (int)($participantById[$actorId]['current_stamina'] ?? self::MAX_STAMINA)));
            $requestedPower = $this->resolvePower($payload);
            $attackPower = min($requestedPower, $actorStamina, self::MAX_POWER);

            if (in_array($type, ['attack', 'spell', 'feint'], true)) {
                $staminaDeltaByParticipant[$actorId] = ($staminaDeltaByParticipant[$actorId] ?? 0) - $attackPower;
            } elseif ($type === 'wait') {
                $staminaDeltaByParticipant[$actorId] = ($staminaDeltaByParticipant[$actorId] ?? 0) + self::WAIT_RECOVERY;
            }

            if ($type === 'defend') {
                $staminaDeltaByParticipant[$actorId] = ($staminaDeltaByParticipant[$actorId] ?? 0) + self::DEFEND_RECOVERY;
            }

            if (in_array($type, ['attack', 'spell', 'feint'], true) && $targetId > 0) {
                $defenderAction = $actionsByActor[$targetId] ?? null;
                $defenderType = is_array($defenderAction) ? (string)($defenderAction['action_type'] ?? 'wait') : 'wait';
                $defenderPayload = $this->decodePayload($defenderAction['payload_json'] ?? null);
                $defenderKind = $this->resolveAttackKind($defenderType, $defenderPayload);
                $defenderStamina = max(0, min(self::MAX_STAMINA, (int)($participantById[$targetId]['current_stamina'] ?? self::MAX_STAMINA)));
                $defenderPower = min($this->resolvePower($defenderPayload), $defenderStamina, self::MAX_POWER);

                $damage = 0;
                $note = 'no_effect';

                if ($defenderType === 'defend') {
                    // Guessing-game rule: if defender meets/beats attack, attack still chips for 1.
                    if ($defenderPower >= $attackPower) {
                        $damage = 1;
                        $note = 'defended_chip_damage';
                    } else {
                        $damage = $attackPower;
                        $note = 'attack_landed';
                    }
                } else {
                    if (in_array($defenderType, ['attack', 'spell', 'feint'], true)) {
                        if ($attackKind === $defenderKind && $defenderPower >= $attackPower) {
                            $damage = 0;
                            $note = 'countered_by_power';
                        } else {
                            $damage = $attackPower;
                            $note = 'attack_landed';
                        }
                    } else {
                        $damage = $attackPower;
                        $note = 'attack_landed';
                    }
                }

                $this->applyDamageToParticipant($targetId, $damage);
                $this->db->update(
                    'combat_round_actions',
                    ['resolution_note', 'outcome_value'],
                    [$note, $damage],
                    ['id'],
                    [(int)$action['id']]
                );
            } elseif ($type === 'forfeit') {
                $this->applyDamageToParticipant($actorId, self::MAX_HP);
                $this->db->update(
                    'combat_round_actions',
                    ['resolution_note', 'outcome_value'],
                    ['forfeit_defeat', -1],
                    ['id'],
                    [(int)$action['id']]
                );
            }

            unset($actorId);
        }

        foreach ($staminaDeltaByParticipant as $participantId => $delta) {
            $this->applyStaminaDeltaToParticipant((int)$participantId, (int)$delta);
        }

        $this->db->update(
            'combat_rounds',
            ['round_state', 'resolved_at'],
            ['resolved', $this->now()],
            ['id'],
            [(int)$round['id']]
        );

        $this->logEvent($instanceId, (int)$round['round_no'], 'round_resolved', ['round_id' => (int)$round['id']]);

        $activeAfter = $this->db->select(
            ['id'],
            'combat_instance_participants',
            ['instance_id', 'participant_state'],
            [$instanceId, 'active']
        );

        if (count($activeAfter) < 2) {
            $this->db->update('combat_instances', ['status', 'last_activity_at'], ['completed', $this->now()], ['id'], [$instanceId]);
            $this->logEvent($instanceId, (int)$round['round_no'], 'instance_completed', ['reason' => 'insufficient active participants']);
            return true;
        }

        $this->touchInstance($instanceId);
        $this->ensureRoundCollecting($instanceId);
        return true;
    }

    private function applyDamageToParticipant(int $participantId, int $damage): void
    {
        $rows = $this->db->select(
            ['id', 'current_hp', 'participant_state'],
            'combat_instance_participants',
            ['id'],
            [$participantId]
        );
        if (count($rows) === 0) {
            return;
        }
        $row = $rows[0];
        if ((string)$row['participant_state'] !== 'active') {
            return;
        }

        $hp = max(0, (int)$row['current_hp'] - max(0, $damage));
        $state = $hp <= 0 ? 'defeated' : 'active';

        $this->db->update(
            'combat_instance_participants',
            ['current_hp', 'participant_state', 'last_seen_at'],
            [$hp, $state, $this->now()],
            ['id'],
            [$participantId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findExistingSharedInstance(string $playerA, string $playerB): ?array
    {
        $sql = <<<'SQL'
SELECT ci.*
FROM combat_instances ci
JOIN combat_instance_participants pa ON pa.instance_id = ci.id AND pa.player_uuid = ? AND pa.participant_state IN ('active','invited')
JOIN combat_instance_participants pb ON pb.instance_id = ci.id AND pb.player_uuid = ? AND pb.participant_state IN ('active','invited')
WHERE ci.status IN ('forming','active')
ORDER BY ci.id DESC
LIMIT 1
SQL;
        $rows = $this->db->manualQuery($sql, [$playerA, $playerB]);
        return is_array($rows) && count($rows) > 0 ? $rows[0] : null;
    }

    /**
     * @param array<int,string> $nearbyUuids
     * @return array<int,array<string,mixed>>
     */
    private function findJoinableInstances(string $playerUuid, array $nearbyUuids): array
    {
        if (count($nearbyUuids) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($nearbyUuids), '?'));
        $params = array_merge([$playerUuid], $nearbyUuids);
        $sql = "SELECT ci.id, ci.host_player_uuid, ci.status, ci.last_round_no, ci.turn_seconds
                FROM combat_instances ci
                LEFT JOIN combat_instance_participants me ON me.instance_id = ci.id AND me.player_uuid = ?
                WHERE ci.status IN ('forming','active')
                  AND ci.host_player_uuid IN ($placeholders)
                  AND me.id IS NULL
                ORDER BY ci.id DESC";

        $rows = $this->db->manualQuery($sql, $params);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findHostOpenInstance(string $hostPlayerUuid): ?array
    {
        $rows = $this->db->select(
            ['*'],
            'combat_instances',
            ['host_player_uuid', 'status'],
            [$hostPlayerUuid, ['forming', 'active']],
            ordered: true,
            orderedBy: 'id',
            ascending: false
        );

        return $rows[0] ?? null;
    }

    private function findActiveInstanceForPlayer(string $playerUuid): ?int
    {
        $sql = <<<'SQL'
SELECT ci.id
FROM combat_instances ci
JOIN combat_instance_participants p ON p.instance_id = ci.id
WHERE p.player_uuid = ?
  AND p.participant_state IN ('active','invited')
  AND ci.status IN ('forming','active')
ORDER BY ci.id DESC
LIMIT 1
SQL;
        $rows = $this->db->manualQuery($sql, [$playerUuid]);
        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }

        return (int)$rows[0]['id'];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getPresence(string $playerUuid): ?array
    {
        $rows = $this->db->select(['*'], 'combat_presence', ['player_uuid'], [$playerUuid]);
        return $rows[0] ?? null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function decodeNearby(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function shouldCloseForRangeDrop(int $instanceId): bool
    {
        $participants = $this->db->select(
            ['player_uuid', 'participant_state', 'last_seen_at'],
            'combat_instance_participants',
            ['instance_id', 'participant_state'],
            [$instanceId, 'active']
        );

        if (count($participants) < 2) {
            return true;
        }

        $aliveUuids = [];
        foreach ($participants as $p) {
            $uuid = trim((string)($p['player_uuid'] ?? ''));
            if ($uuid !== '') {
                $aliveUuids[] = $uuid;
            }
        }

        if (count($aliveUuids) < 2) {
            return true;
        }

        $presenceByUuid = [];
        foreach ($aliveUuids as $uuid) {
            $presence = $this->getPresence($uuid);
            if ($presence === null) {
                continue;
            }

            $lastSeen = new DateTimeImmutable((string)$presence['last_seen_at']);
            if ((new DateTimeImmutable('now'))->getTimestamp() - $lastSeen->getTimestamp() > self::PRESENCE_STALE_SECONDS) {
                continue;
            }
            $presenceByUuid[$uuid] = $this->decodeNearby($presence['nearby_json'] ?? null);
        }

        if (count($presenceByUuid) < 2) {
            return true;
        }

        foreach ($presenceByUuid as $uuid => $nearbyList) {
            $nearbySet = [];
            foreach ($nearbyList as $entry) {
                $candidate = trim((string)($entry['player_uuid'] ?? ''));
                if ($candidate !== '') {
                    $nearbySet[$candidate] = true;
                }
            }
            foreach ($aliveUuids as $other) {
                if ($other === $uuid) {
                    continue;
                }
                if (isset($nearbySet[$other])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isPlayerNearby(string $observerPlayerUuid, string $targetPlayerUuid): bool
    {
        $presence = $this->getPresence($observerPlayerUuid);
        if ($presence === null) {
            return false;
        }

        $lastSeen = new DateTimeImmutable((string)$presence['last_seen_at']);
        if ((new DateTimeImmutable('now'))->getTimestamp() - $lastSeen->getTimestamp() > self::PRESENCE_STALE_SECONDS) {
            return false;
        }

        $nearby = $this->decodeNearby($presence['nearby_json'] ?? null);
        foreach ($nearby as $entry) {
            $uuid = trim((string)($entry['player_uuid'] ?? ''));
            if ($uuid !== '' && $uuid === $targetPlayerUuid) {
                return true;
            }
        }

        return false;
    }

    private function logEvent(int $instanceId, int $roundNo, string $eventType, array $event): void
    {
        $this->db->insert(
            'combat_events',
            ['instance_id', 'round_no', 'event_type', 'event_json', 'created_at'],
            [
                $instanceId,
                $roundNo,
                $eventType,
                json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $this->now(),
            ]
        );
    }

    private function touchInstance(int $instanceId): void
    {
        $this->db->update(
            'combat_instances',
            ['updated_at', 'last_activity_at'],
            [$this->now(), $this->now()],
            ['id'],
            [$instanceId]
        );
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (float)$value;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodePayload(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function resolveAttackKind(string $actionType, array $payload): string
    {
        $kind = strtolower(trim((string)($payload['attack_kind'] ?? '')));
        if ($kind === 'physical' || $kind === 'magical') {
            return $kind;
        }
        return $actionType === 'spell' ? 'magical' : 'physical';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function resolvePower(array $payload): int
    {
        $power = $payload['power'] ?? 5;
        if (!is_numeric($power)) {
            return 5;
        }
        return max(0, min(self::MAX_POWER, (int)$power));
    }

    private function applyStaminaDeltaToParticipant(int $participantId, int $delta): void
    {
        $rows = $this->db->select(
            ['id', 'current_stamina'],
            'combat_instance_participants',
            ['id'],
            [$participantId]
        );
        if (count($rows) === 0) {
            return;
        }

        $current = (int)($rows[0]['current_stamina'] ?? self::MAX_STAMINA);
        $next = max(0, min(self::MAX_STAMINA, $current + $delta));
        if ($next === $current) {
            return;
        }

        $this->db->update(
            'combat_instance_participants',
            ['current_stamina', 'last_seen_at'],
            [$next, $this->now()],
            ['id'],
            [$participantId]
        );
    }

    private function pickDefaultTargetParticipantId(int $instanceId, int $actorParticipantId): ?int
    {
        $active = $this->db->select(
            ['id'],
            'combat_instance_participants',
            ['instance_id', 'participant_state'],
            [$instanceId, 'active'],
            ordered: true,
            orderedBy: 'id',
            ascending: true
        );

        foreach ($active as $row) {
            $pid = (int)($row['id'] ?? 0);
            if ($pid > 0 && $pid !== $actorParticipantId) {
                return $pid;
            }
        }

        return null;
    }
}
