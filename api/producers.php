<?php
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query('SELECT * FROM producers ORDER BY name ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $producers = array_map(function ($r) {
            return [
                'id' => (string) $r['id'],
                'name' => $r['name'],
                'region' => $r['region'],
                'description' => $r['description'],
            ];
        }, $rows);
        echo json_encode($producers);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'name is verplicht']);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO producers (name, region, description) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE region = VALUES(region), description = VALUES(description)');
        $stmt->execute([
            $data['name'],
            $data['region'] ?? '',
            $data['description'] ?? '',
        ]);
        echo json_encode(['ok' => true]);
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
        $sets = [];
        $vals = [];
        foreach (['name', 'region', 'description'] as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "$f = ?";
                $vals[] = $data[$f];
            }
        }
        if (!empty($sets)) {
            $vals[] = $id;
            $stmt = $pdo->prepare('UPDATE producers SET ' . implode(', ', $sets) . ' WHERE id = ?');
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
        $stmt = $pdo->prepare('DELETE FROM producers WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'methode niet toegestaan']);
}
