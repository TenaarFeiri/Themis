<?php
require_once __DIR__ . '/../../../html/themis/Autoloader.php';

use Themis\System\ThemisDB;
use Themis\System\ThemisDBException;

echo "Testing ThemisDB...\n";

try {
    $db = new ThemisDB();
    $db->connect();
    echo "[PASS] Connected to database.\n";

    // Test 1: Select
    echo "Testing SELECT...\n";
    $rows = $db->table('players')->select(columns: ['player_id', 'player_uuid'])->limit(1)->get();
    if (is_array($rows)) {
        echo "[PASS] SELECT returned " . count($rows) . " rows.\n";
        if (count($rows) > 0) {
            echo "      First row ID: " . $rows[0]['player_id'] . "\n";
        }
    } else {
        echo "[FAIL] SELECT did not return an array.\n";
    }

    // Test 2: Fluent Where
    echo "Testing WHERE...\n";
    $rows = $db->table('players')
        ->where(column: 'player_id', operator: '>', value: 0)
        ->limit(1)
        ->get();
    echo "[PASS] WHERE query executed.\n";

    // Test 3: Safety Rail (Invalid Table)
    echo "Testing Invalid Table Safety...\n";
    try {
        $db->table('non_existent_table')->get();
        echo "[FAIL] Did not catch invalid table.\n";
    } catch (ThemisDBException $e) {
        echo "[PASS] Caught invalid table: " . $e->getMessage() . "\n";
    }

    // Test 4: Destructive Query Safety
    echo "Testing Destructive Query Safety...\n";
    try {
        $db->manualQuery("DELETE FROM players WHERE player_id = 1");
        echo "[FAIL] Did not catch DELETE query.\n";
    } catch (ThemisDBException $e) {
        echo "[PASS] Caught DELETE query: " . $e->getMessage() . "\n";
    }

    // Test 5: Insert Player
    echo "Testing INSERT...\n";
    $uuid = 'test-uuid-' . uniqid();
    $name = 'Test User ' . uniqid();
    $data = [
        'player_name' => $name,
        'player_uuid' => $uuid,
        'player_created' => date('Y-m-d H:i:s'),
        'player_last_online' => date('Y-m-d H:i:s')
    ];

    $id = $db->table('players')->insert($data);

    if (is_int($id) && $id > 0) {
        echo "[PASS] Inserted player with ID: $id\n";

        // Verify data
        $player = $db->table('players')->where(column: 'player_id', operator: '=', value: $id)->first();
        if ($player && $player['player_name'] === $name) {
            echo "[PASS] Verified inserted data matches.\n";
        } else {
            echo "[FAIL] Inserted data verification failed.\n";
            print_r($player);
        }
    } else {
        echo "[FAIL] Insert failed.\n";
    }

} catch (Throwable $e) {
    echo "[FATAL] Uncaught exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
