<?php
// Inloggen / wachtwoord instellen.
// GET               → {auth: bool, setup: bool}  (setup = er is nog geen wachtwoord gekozen)
// POST {password}   → inloggen; bij de allereerste keer wordt dit het wachtwoord
// DELETE            → uitloggen op dit apparaat
header('Content-Type: application/json');
require __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(['auth' => auth_ok(), 'setup' => auth_settings() === null]);
    exit;
}

if ($method === 'DELETE') {
    auth_clear_cookie();
    echo json_encode(['ok' => true]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'methode niet toegestaan']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$password = (string) ($data['password'] ?? '');
$settings = auth_settings();

if ($settings === null) {
    // Eerste keer: wachtwoord kiezen
    if (mb_strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Kies een wachtwoord van minstens 6 tekens.']);
        exit;
    }
    $settings = ['hash' => password_hash($password, PASSWORD_DEFAULT), 'secret' => bin2hex(random_bytes(32))];
    $php = "<?php\n// Aangemaakt door login.php. Niet in git. Verwijderen = opnieuw een wachtwoord kiezen.\nreturn " . var_export($settings, true) . ";\n";
    if (@file_put_contents(AUTH_FILE, $php, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Kon het wachtwoord niet opslaan op de server (schrijfrechten in de map api).']);
        exit;
    }
    @chmod(AUTH_FILE, 0600);
    auth_set_cookie($settings['secret']);
    echo json_encode(['ok' => true, 'created' => true]);
    exit;
}

if (!password_verify($password, $settings['hash'])) {
    sleep(1); // raden vertragen
    http_response_code(403);
    echo json_encode(['error' => 'Onjuist wachtwoord.']);
    exit;
}

auth_set_cookie($settings['secret']);
echo json_encode(['ok' => true]);
