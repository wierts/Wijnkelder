<?php
// Drinkhistorie.
// GET            → alle proefmomenten, nieuwste eerst
// POST           → {wineId, rating 1-5|null, note, rebuy, date?} : slaat het moment op en
//                  haalt één fles van de voorraad af (laatste fles → wijn op "opgedronken")
// DELETE ?id=..  → proefmoment verwijderen (voorraad blijft ongemoeid)
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $rows = $pdo->query('SELECT * FROM tastings ORDER BY drunk_on DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array_map(function ($r) {
            return [
                'id' => (string) $r['id'],
                'wineId' => $r['wine_id'] !== null ? (string) $r['wine_id'] : null,
                'date' => $r['drunk_on'],
                'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
                'note' => $r['note'] ?? '',
                'rebuy' => (bool) $r['rebuy'],
                'name' => $r['wine_name'],
                'producer' => $r['wine_producer'],
                'year' => $r['wine_year'] !== null ? (int) $r['wine_year'] : null,
                'region' => $r['wine_region'],
                'grape' => $r['wine_grape'],
                'color' => $r['wine_color'],
                'country' => $r['wine_country'],
            ];
        }, $rows));
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $wineId = (int) ($data['wineId'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM wines WHERE id = ?');
        $stmt->execute([$wineId]);
        $wine = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wine) {
            http_response_code(404);
            echo json_encode(['error' => 'wijn niet gevonden']);
            exit;
        }
        $rating = isset($data['rating']) ? (int) $data['rating'] : 0;
        $rating = ($rating >= 1 && $rating <= 5) ? $rating : null;
        $date = (isset($data['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) ? $data['date'] : date('Y-m-d');

        $pdo->beginTransaction();
        $ins = $pdo->prepare('INSERT INTO tastings (wine_id, drunk_on, rating, note, rebuy, wine_name, wine_producer, wine_year, wine_region, wine_grape, wine_color, wine_country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([
            $wineId, $date, $rating, trim((string) ($data['note'] ?? '')), !empty($data['rebuy']) ? 1 : 0,
            $wine['name'], $wine['producer'] ?? '', $wine['year'], $wine['region'] ?? '', $wine['grape'] ?? '',
            $wine['color'] ?? '', $wine['country'] ?? '',
        ]);
        $tastingId = (string) $pdo->lastInsertId();
        $qty = max(1, (int) ($wine['qty'] ?? 1));
        if ($qty > 1) {
            $pdo->prepare('UPDATE wines SET qty = qty - 1 WHERE id = ?')->execute([$wineId]);
        } else {
            $pdo->prepare('UPDATE wines SET drunk = 1 WHERE id = ?')->execute([$wineId]);
        }
        $pdo->commit();
        echo json_encode(['id' => $tastingId, 'qtyLeft' => $qty > 1 ? $qty - 1 : 0]);
        break;

    case 'DELETE':
        parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
        $id = (int) ($q['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id is verplicht']);
            exit;
        }
        $pdo->prepare('DELETE FROM tastings WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'methode niet toegestaan']);
}
