<?php
// Home Assistant-koppeling (v2.5.0).
// GET met header "X-Wijnkelder-Token: <sleutel>" (of "Authorization: Bearer <sleutel>")
//                                                 → samenvatting van de kelder voor een REST-sensor
// GET  (ingelogd in de app)                      → {enabled: bool}
// POST (ingelogd in de app)                      → nieuwe sleutel maken; wordt één keer getoond
// DELETE (ingelogd in de app)                    → koppeling uitzetten
//
// Alleen een hash van de sleutel staat op de server, in api/ha-token.php (niet in git).
// Sleutel kwijt? Maak in de app een nieuwe; de oude werkt dan niet meer.

define('HA_TOKEN_FILE', __DIR__ . '/ha-token.php');

function ha_hash() {
    if (!is_file(HA_TOKEN_FILE)) return null;
    $s = include HA_TOKEN_FILE;
    return (is_array($s) && !empty($s['hash'])) ? $s['hash'] : null;
}
function ha_bearer() {
    // Eigen header werkt altijd; Authorization wordt door sommige PHP-FPM-instellingen weggefilterd
    $x = trim($_SERVER['HTTP_X_WIJNKELDER_TOKEN'] ?? '');
    if (preg_match('/^[A-Za-z0-9_-]{20,}$/', $x)) return $x;
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$h && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') $h = $v;
    }
    return preg_match('/^Bearer\s+([A-Za-z0-9_-]{20,})$/', trim($h), $m) ? $m[1] : null;
}

$method = $_SERVER['REQUEST_METHOD'];
$bearer = ha_bearer();

if ($method === 'GET' && $bearer !== null) {
    $hash = ha_hash();
    if (!$hash || !hash_equals($hash, hash('sha256', $bearer))) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'ongeldige sleutel']);
        exit;
    }
    define('WK_TOKEN_AUTH', true); // db.php slaat de cookie-check dan over
    require __DIR__ . '/db.php';
    echo json_encode(ha_summary($pdo), JSON_UNESCAPED_UNICODE);
    exit;
}

// Beheer vanuit de app: gewone login (cookie) vereist
require __DIR__ . '/db.php';

switch ($method) {
    case 'GET':
        echo json_encode(['enabled' => ha_hash() !== null]);
        break;
    case 'POST':
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $php = "<?php\n// Aangemaakt door ha.php. Niet in git. Verwijderen = Home Assistant-koppeling uit.\nreturn " .
            var_export(['hash' => hash('sha256', $token), 'created' => date('c')], true) . ";\n";
        if (@file_put_contents(HA_TOKEN_FILE, $php, LOCK_EX) === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Kon de sleutel niet opslaan op de server (schrijfrechten in de map api).']);
            exit;
        }
        @chmod(HA_TOKEN_FILE, 0600);
        echo json_encode(['token' => $token]);
        break;
    case 'DELETE':
        if (is_file(HA_TOKEN_FILE)) @unlink(HA_TOKEN_FILE);
        echo json_encode(['ok' => true]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'methode niet toegestaan']);
}

// Zelfde regels als autoStatus() in index.html, zodat Home Assistant en de app gelijk lopen
function ha_window($r) {
    $from = $r['window_from'] !== null ? (int) $r['window_from'] : null;
    $to = $r['window_to'] !== null ? (int) $r['window_to'] : null;
    if ($from === null || $to === null) {
        $t = strtolower((string) $r['window_note']);
        preg_match_all('/\b(?:19|20)\d{2}\b/', $t, $m);
        $years = array_map('intval', $m[0]);
        $pf = $pt = null;
        if (count($years) >= 2) { $pf = min($years); $pt = max($years); }
        elseif (count($years) === 1) {
            if (preg_match('/vanaf|from|na\b|after/', $t)) $pf = $years[0]; else $pt = $years[0];
        }
        if ($from === null) $from = $pf;
        if ($to === null) $to = $pt;
    }
    return [$from, $to];
}
function ha_status($r, $year) {
    [$from, $to] = ha_window($r);
    if (!$from && !$to) return in_array($r['status'], ['now', 'soon', 'wait', 'past'], true) ? $r['status'] : 'wait';
    if ($to && $year > $to) return 'past';
    if ($from && $year < $from) return $from - $year <= 2 ? 'soon' : 'wait';
    return 'now';
}
function ha_label($r) {
    $name = trim(implode(' ', array_filter([$r['producer'] ?? '', $r['name'], $r['year'] ?? ''])));
    $loc = trim((string) ($r['location'] ?? ''));
    if ($loc !== '') $name .= ' (Rek ' . $loc . ')';
    return $name;
}
function ha_summary($pdo) {
    $year = (int) date('Y');
    $rows = $pdo->query('SELECT * FROM wines WHERE drunk = 0')->fetchAll(PDO::FETCH_ASSOC);
    $count = ['now' => 0, 'soon' => 0, 'wait' => 0, 'past' => 0];
    $lists = ['now' => [], 'past' => []];
    $bottles = 0;
    foreach ($rows as $r) {
        $q = max(1, (int) ($r['qty'] ?? 1));
        $st = ha_status($r, $year);
        $bottles += $q;
        $count[$st] += $q;
        if (isset($lists[$st])) $lists[$st][] = ['label' => ha_label($r), 'to' => ha_window($r)[1] ?? 9999];
    }
    foreach ($lists as &$l) {
        usort($l, fn($a, $b) => $a['to'] <=> $b['to']);
        $l = array_slice(array_column($l, 'label'), 0, 10);
    }
    unset($l);
    $last = $pdo->query('SELECT * FROM tastings ORDER BY drunk_on DESC, id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $st = $pdo->prepare('SELECT COUNT(*) FROM tastings WHERE YEAR(drunk_on) = ?');
    $st->execute([$year]);
    return [
        'flessen' => $bottles,
        'wijnen' => count($rows),
        'nu_drinken' => $count['now'],
        'binnenkort' => $count['soon'],
        'kan_wachten' => $count['wait'],
        'over_de_top' => $count['past'],
        'nu_drinken_lijst' => $lists['now'],
        'over_de_top_lijst' => $lists['past'],
        'gedronken_dit_jaar' => (int) $st->fetchColumn(),
        'laatst_gedronken' => $last ? [
            'datum' => date('d-m-Y', strtotime($last['drunk_on'])),
            'wijn' => trim(implode(' ', array_filter([$last['wine_producer'], $last['wine_name'], $last['wine_year']]))),
            'score' => $last['rating'] !== null ? (int) $last['rating'] : null,
        ] : null,
        'bijgewerkt' => date('c'),
    ];
}
