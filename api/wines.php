<?php
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query('SELECT * FROM wines ORDER BY id DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $wines = array_map(function ($r) {
            return [
                'id' => (string) $r['id'],
                'name' => $r['name'],
                'grape' => $r['grape'],
                'region' => $r['region'],
                'year' => $r['year'] !== null ? (int) $r['year'] : null,
                'status' => $r['status'],
                'window' => $r['window_note'],
                'character' => $r['character_note'],
                'serving' => json_decode($r['serving_json'] ?: '[]'),
                'drunk' => (bool) $r['drunk'],
            ];
        }, $rows);
        echo json_encode($wines);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'name is verplicht']);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO wines (name, grape, region, year, status, window_note, character_note, serving_json, drunk) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)');
        $stmt->execute([
            $data['name'],
            $data['grape'] ?? '',
            $data['region'] ?? '',
            $data['year'] ?? null,
            $data['status'] ?? 'wait',
            $data['window'] ?? '',
            $data['character'] ?? '',
            json_encode($data['serving'] ?? []),
        ]);
        echo json_encode(['id' => (string) $pdo->lastInsertId()]);
        break;

    case 'PATCH':
        parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
        $id = $q['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id is verplicht']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        if (array_key_exists('drunk', $data)) {
            $stmt = $pdo->prepare('UPDATE wines SET drunk = ? WHERE id = ?');
            $stmt->execute([$data['drunk'] ? 1 : 0, $id]);
        }
        echo json_encode(['ok' => true]);
        break;

    case 'DELETE':
        parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
        $id = $q['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id is verplicht']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM wines WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'methode niet toegestaan']);
}
