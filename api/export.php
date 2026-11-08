<?php
// Back-up en export (v2.3.0). Alleen voor ingelogde apparaten (via db.php).
// GET ?format=json               → volledige back-up: wijnen, drinkhistorie en producenten
//                                   precies zoals ze in de database staan (alle kolommen)
// GET ?format=csv&table=wines    → kelder als CSV voor Excel (puntkomma, Nederlandse notatie)
// GET ?format=csv&table=tastings → drinkhistorie als CSV
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'methode niet toegestaan']);
    exit;
}

$format = $_GET['format'] ?? 'json';
$table = $_GET['table'] ?? 'wines';
$stamp = date('Y-m-d');

header('Cache-Control: no-store');

if ($format === 'json') {
    $out = [
        'app' => 'Mijn Wijnkelder',
        'export' => date('c'),
        'wines' => $pdo->query('SELECT * FROM wines ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        'tastings' => $pdo->query('SELECT * FROM tastings ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        'producers' => $pdo->query('SELECT * FROM producers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="wijnkelder-backup-' . $stamp . '.json"');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($format !== 'csv' || !in_array($table, ['wines', 'tastings'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'onbekend formaat of tabel']);
    exit;
}

const STATUS_NL = ['now' => 'Nu drinken', 'soon' => 'Binnenkort', 'wait' => 'Kan wachten', 'past' => 'Over de top'];

function nl_date($v) {
    return ($v && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) ? "$m[3]-$m[2]-$m[1]" : '';
}
function nl_money($v) {
    return ($v === null || $v === '') ? '' : number_format((float) $v, 2, ',', '');
}

if ($table === 'wines') {
    $head = ['ID', 'Producent', 'Naam', 'Jaargang', 'Kleur', 'Land', 'Regio', 'Druif', 'Flessen', 'Formaat (ml)',
        'Locatie', 'Status', 'Drinken vanaf', 'Drinken tot', 'Opgedronken', 'Aankoopdatum', 'Aankoopprijs (€)',
        'Marktprijs (€)', 'Gekocht bij', 'Winkel/wijngoed', 'Notities', 'Toegevoegd op'];
    $rows = array_map(function ($r) {
        return [
            $r['id'], $r['producer'] ?? '', $r['name'], $r['year'] ?? '', $r['color'] ?? '', $r['country'] ?? '',
            $r['region'], $r['grape'], $r['drunk'] ? 0 : ($r['qty'] ?? 1), $r['size'] ?? 750,
            $r['location'] ?? '', STATUS_NL[$r['status']] ?? $r['status'], $r['window_from'] ?? '', $r['window_to'] ?? '',
            $r['drunk'] ? 'ja' : 'nee', nl_date($r['purchase_date'] ?? ''), nl_money($r['purchase_price'] ?? null),
            nl_money($r['market_price'] ?? null), $r['purchase_source'] ?? '', $r['purchase_place'] ?? '',
            $r['notes'] ?? '', nl_date($r['created_at'] ?? ''),
        ];
    }, $pdo->query('SELECT * FROM wines ORDER BY producer, name, year')->fetchAll(PDO::FETCH_ASSOC));
    $name = 'wijnkelder-kelder-' . $stamp . '.csv';
} else {
    $head = ['Datum', 'Producent', 'Naam', 'Jaargang', 'Kleur', 'Land', 'Regio', 'Druif', 'Score (1-5)', 'Opnieuw kopen', 'Notitie'];
    $rows = array_map(function ($r) {
        return [
            nl_date($r['drunk_on']), $r['wine_producer'], $r['wine_name'], $r['wine_year'] ?? '', $r['wine_color'],
            $r['wine_country'], $r['wine_region'], $r['wine_grape'], $r['rating'] ?? '', $r['rebuy'] ? 'ja' : 'nee', $r['note'] ?? '',
        ];
    }, $pdo->query('SELECT * FROM tastings ORDER BY drunk_on DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC));
    $name = 'wijnkelder-historie-' . $stamp . '.csv';
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
$fh = fopen('php://output', 'w');
fwrite($fh, "\xEF\xBB\xBF"); // BOM, zodat Excel de accenten goed toont
fputcsv($fh, $head, ';', '"', '');
foreach ($rows as $row) fputcsv($fh, $row, ';', '"', '');
fclose($fh);
