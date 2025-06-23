<?php

namespace Themis\Controller;

use Themis\System\SystemDataStorage;
use Themis\Controller\DatabaseController;
use Exception;

class TestController
{
    private SystemDataStorage $systemData;

    public function __construct(SystemDataStorage $systemData)
    {
        $this->systemData = $systemData;
    }

    /**
     * Insert a new player row for testing, then select it back to verify.
     * @return array
     */
    public function insertNewPlayerForTest(): array
    {
        try {
            $dbController = new DatabaseController($this->systemData);

            // Generate test values
            $playerName = 'TestUser_' . bin2hex(random_bytes(3));
            $playerUuid = 'testuuid_' . bin2hex(random_bytes(8));
            $now = date('Y-m-d H:i:s');
            $playerCreated = $now;
            $playerLastOnline = $now;
            $playerTitlerUrl = 'titler_' . bin2hex(random_bytes(2));
            $playerHudUrl = 'hud_' . bin2hex(random_bytes(2));

            $insertCmd = [
                'method' => 'insert',
                'table' => 'players',
                'columns' => [
                    'player_name',
                    'player_uuid',
                    'player_created',
                    'player_last_online',
                    'player_titler_url',
                    'player_hud_url'
                ],
                'values' => [
                    $playerName,
                    $playerUuid,
                    $playerCreated,
                    $playerLastOnline,
                    $playerTitlerUrl,
                    $playerHudUrl
                ],
                'methodOptions' => ''
            ];
            echo "[insertNewPlayerForTest] Insert Query: ", var_export($insertCmd, true), PHP_EOL;
            $this->systemData->storeData('dbCommand', $insertCmd);
            $insertResult = $dbController->execute();
            if (!is_array($insertResult) || $insertResult[0] !== 0) {
                $err = $insertResult[2] ?? 'Unknown error';
                throw new Exception("Database error in insert: $err");
            }

            // Try to get the new player_id (assume lastInsertId is returned in $insertResult[2]['lastInsertId'] or similar)
            $newPlayerId = null;
            if (isset($insertResult[2]['lastInsertId'])) {
                $newPlayerId = $insertResult[2]['lastInsertId'];
            } elseif (isset($insertResult[2][0]['player_id'])) {
                $newPlayerId = $insertResult[2][0]['player_id'];
            }

            // Fallback: select by UUID if no lastInsertId
            if (!$newPlayerId) {
                $selectCmd = [
                    'method' => 'select',
                    'select' => ['*'],
                    'from' => 'players',
                    'where' => ['player_uuid'],
                    'equals' => [$playerUuid],
                    'options' => ''
                ];
                $this->systemData->storeData('dbCommand', $selectCmd);
                $selectResult = $dbController->execute();
                if (is_array($selectResult) && isset($selectResult[2][0]['player_id'])) {
                    $newPlayerId = $selectResult[2][0]['player_id'];
                }
            }

            if (!$newPlayerId) {
                throw new Exception('Could not determine new player_id after insert.');
            }

            // Select the new row for verification
            $selectCmd = [
                'method' => 'select',
                'select' => ['*'],
                'from' => 'players',
                'where' => ['player_id'],
                'equals' => [$newPlayerId],
                'options' => ''
            ];
            $this->systemData->storeData('dbCommand', $selectCmd);
            $verifyResult = $dbController->execute();
            $newRow = $verifyResult[2][0] ?? null;

            return [0, false, [
                'inserted_player_id' => $newPlayerId,
                'inserted_row' => $newRow,
                'insert_result' => $insertResult
            ]];
        } catch (Exception $e) {
            return [1, false, $e->getMessage()];
        }
    }

    /**
     * For MasterController compatibility: run the insert test.
     */
    public function execute(): array
    {
        return $this->insertNewPlayerForTest();
    }
}
