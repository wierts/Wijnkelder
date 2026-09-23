<?php
require __DIR__ . '/db.php';

const ALLOWED_COLORS = ['', 'rood', 'wit', 'rose', 'mousserend', 'dessert'];

function clean_color($v) {
    $v = strtolower(trim((string) $v));
    return in_array($v, ALLOWED_COLORS, true) ? $v : '';
}

function clean_country($v) {
    $v = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $v));
    return strlen($v) === 2 ? $v : '';
}

function clean_location($v) {
    return mb_substr(trim((string) $v), 0, 50);
}

function clean_year($v) {
    $v = (int) $v;
    return ($v >= 1900 && $v <= 2200) ? $v : null;
}

function clean_date($v) {
    $v = trim((string) $v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function clean_price($v) {
    if ($v === null || $v === '') return null;
    $v = (float) str_replace(',', '.', (string) $v);
    return $v >= 0 ? round($v, 2) : null;
}

function clean_notes($v) {
    return trim((string) $v);
}

function clean_short($v) {
    return mb_substr(trim((string) $v), 0, 160);
}

// Alleen eigen uploads (img/etiketten/...) of een https-link naar een afbeelding.
function clean_image($v) {
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('#^img/etiketten/[A-Za-z0-9._-]+$#', $v)) return $v;
    if (preg_match('#^https://[^\s"<>]+$#i', $v)) return mb_substr($v, 0, 500);
    return '';
}

// Extra velden die via POST en PATCH gezet kunnen worden: JSON-sleutel => [kolom, opschoonfunctie]
const EXTRA_FIELDS = [
    'color' => ['color', 'clean_color'],
    'country' => ['country', 'clean_country'],
    'location' => ['location', 'clean_location'],
    'windowFrom' => ['window_from', 'clean_year'],
    'windowTo' => ['window_to', 'clean_year'],
    'purchaseDate' => ['purchase_date', 'clean_date'],
    'purchasePrice' => ['purchase_price', 'clean_price'],
    'notes' => ['notes', 'clean_notes'],
    'image' => ['image', 'clean_image'],
    'marketPrice' => ['market_price', 'clean_price'],
    'marketNote' => ['market_note', 'clean_short'],
];

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
                'color' => $r['color'] ?? '',
                'country' => $r['country'] ?? '',
                'location' => $r['location'] ?? '',
                'windowFrom' => isset($r['window_from']) ? (int) $r['window_from'] : null,
                'windowTo' => isset($r['window_to']) ? (int) $r['window_to'] : null,
                'purchaseDate' => $r['purchase_date'] ?? null,
                'purchasePrice' => isset($r['purchase_price']) ? (float) $r['purchase_price'] : null,
                'notes' => $r['notes'] ?? '',
                'image' => $r['image'] ?? '',
                'marketPrice' => isset($r['market_price']) ? (float) $r['market_price'] : null,
                'marketNote' => $r['market_note'] ?? '',
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
        $cols = ['name', 'grape', 'region', 'year', 'status', 'window_note', 'character_note', 'serving_json', 'drunk', 'qty', 'producer'];
        $vals = [
            $data['name'],
            $data['grape'] ?? '',
            $data['region'] ?? '',
            $data['year'] ?? null,
            $data['status'] ?? 'wait',
            $data['window'] ?? '',
            $data['character'] ?? '',
            json_encode($data['serving'] ?? []),
            0,
            $data['qty'] ?? 1,
            $data['producer'] ?? '',
        ];
        foreach (EXTRA_FIELDS as $key => [$col, $clean]) {
            $cols[] = $col;
            $vals[] = $clean($data[$key] ?? null);
        }
        $stmt = $pdo->prepare('INSERT INTO wines (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
        $stmt->execute($vals);
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
        foreach (EXTRA_FIELDS as $key => [$col, $clean]) {
            if (array_key_exists($key, $data)) {
                $sets[] = "$col = ?";
                $vals[] = $clean($data[$key]);
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
