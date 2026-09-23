<?php
// Toegangscontrole voor de API: één keer per apparaat inloggen met een wachtwoord.
// Na inloggen krijgt het apparaat een beveiligde cookie. Die wordt bij elk gebruik
// verlengd, dus zolang je de app af en toe opent blijf je ingelogd.
//
// Het wachtwoord (als hash) en een geheime sleutel staan in api/wachtwoord.php.
// Dat bestand wordt de eerste keer vanuit de app aangemaakt ("Kies een wachtwoord"),
// staat in .gitignore en komt dus nooit op GitHub.
// Wachtwoord vergeten of alle apparaten uitloggen? Verwijder api/wachtwoord.php
// via Plesk Bestandsbeheer en kies in de app een nieuw wachtwoord.

const AUTH_COOKIE = 'wijnkelder_auth';
const AUTH_DAYS = 400; // maximum dat browsers toestaan; wordt bij elk gebruik verlengd
define('AUTH_FILE', __DIR__ . '/wachtwoord.php');

function auth_settings() {
    if (!file_exists(AUTH_FILE)) return null;
    $s = include AUTH_FILE;
    return (is_array($s) && !empty($s['hash']) && !empty($s['secret'])) ? $s : null;
}

function auth_sign($expires, $secret) {
    return hash_hmac('sha256', 'wijnkelder|' . $expires, $secret);
}

function auth_set_cookie($secret) {
    $expires = time() + AUTH_DAYS * 86400;
    $value = $expires . '.' . auth_sign($expires, $secret);
    setcookie(AUTH_COOKIE, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_clear_cookie() {
    setcookie(AUTH_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}

// true als dit apparaat een geldige cookie heeft (en verlengt die dan)
function auth_ok() {
    $s = auth_settings();
    if (!$s) return false;
    $c = $_COOKIE[AUTH_COOKIE] ?? '';
    if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $c, $m)) return false;
    if ((int) $m[1] < time()) return false;
    if (!hash_equals(auth_sign($m[1], $s['secret']), $m[2])) return false;
    // Verlengen, maar hooguit één keer per dag
    if ((int) $m[1] - time() < (AUTH_DAYS - 1) * 86400) auth_set_cookie($s['secret']);
    return true;
}

// Aanroepen bovenaan elk API-bestand: stopt met 401 als het apparaat niet is ingelogd
function require_auth() {
    if (auth_ok()) return;
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'login_required', 'setup' => auth_settings() === null]);
    exit;
}
