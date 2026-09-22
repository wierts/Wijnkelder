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
                'qty' => isset($r['qty']) ? (int) $r['qty'] : 1,
                'producer' => $r['producer'] ?? '',
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
        $stmt = $pdo->prepare('INSERT INTO wines (name, grape, region, year, status, window_note, character_note, serving_json, drunk, qty, producer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)');
        $stmt->execute([
            $data['name'],
            $data['grape'] ?? '',
            $data['region'] ?? '',
            $data['year'] ?? null,
            $data['status'] ?? 'wait',
            $data['window'] ?? '',
            $data['character'] ?? '',
            json_encode($data['serving'] ?? []),
            $data['qty'] ?? 1,
            $data['producer'] ?? '',
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

        $fieldMap = [
            'name' => 'name',
            'producer' => 'producer',
            'grape' => 'grape',
            'region' => 'region',
            'year' => 'year',
            'status' => 'status',
            'window' => 'window_note',
            'character' => 'character_note',
        ];
        $sets = [];
        $vals = [];
        foreach ($fieldMap as $key => $col) {
            if (array_key_exists($key, $data)) {
                $sets[] = "$col = ?";
                $vals[] = $data[$key];
            }
        }
        if (array_key_exists('serving', $data)) {
            $sets[] = 'serving_json = ?';
            $vals[] = json_encode($data['serving']);
        }
        if (array_key_exists('drunk', $data)) {
            $sets[] = 'drunk = ?';
            $vals[] = $data['drunk'] ? 1 : 0;
        }
        if (array_key_exists('qty', $data)) {
            $sets[] = 'qty = ?';
            $vals[] = max(1, (int) $data['qty']);
        }
        if (!empty($sets)) {
            $vals[] = $id;
            $stmt = $pdo->prepare('UPDATE wines SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($vals);
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
