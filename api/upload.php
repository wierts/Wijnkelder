<?php
// Etiketfoto uploaden: POST multipart met velden "id" (wijn-id) en "photo" (afbeelding).
// De foto wordt verkleind tot max. 900 px en als JPEG opgeslagen in img/etiketten/.
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'methode niet toegestaan']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$file = $_FILES['photo'] ?? null;
if (!$id || !$file || $file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'id en foto zijn verplicht (max. ' . ini_get('upload_max_filesize') . ')']);
    exit;
}
if ($file['size'] > 15 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'foto is groter dan 15 MB']);
    exit;
}

$info = @getimagesize($file['tmp_name']);
$allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
if (!$info || !in_array($info[2], $allowed, true)) {
    http_response_code(415);
    echo json_encode(['error' => 'alleen JPEG, PNG of WebP']);
    exit;
}

$dir = dirname(__DIR__) . '/img/etiketten';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'map img/etiketten kan niet worden aangemaakt']);
    exit;
}

$name = 'wijn-' . $id . '-' . time() . '.jpg';
$target = $dir . '/' . $name;
$saved = false;

// Verkleinen en opnieuw coderen met GD (haalt meteen eventuele rommel uit het bestand).
if (function_exists('imagecreatefromstring')) {
    $src = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if ($src) {
        // Telefoonfoto's: EXIF-oriëntatie toepassen
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name']);
            $rot = ['3' => 180, '6' => -90, '8' => 90][(string) ($exif['Orientation'] ?? '')] ?? 0;
            if ($rot) $src = imagerotate($src, $rot, 0);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 900 / max($w, $h));
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $saved = imagejpeg($dst, $target, 84);
    }
}
if (!$saved) {
    // Geen GD beschikbaar: alleen JPEG direct overnemen
    if ($info[2] !== IMAGETYPE_JPEG || !move_uploaded_file($file['tmp_name'], $target)) {
        http_response_code(500);
        echo json_encode(['error' => 'foto kon niet worden opgeslagen']);
        exit;
    }
}

$path = 'img/etiketten/' . $name;

// Oude eigen upload opruimen
$old = $pdo->prepare('SELECT image FROM wines WHERE id = ?');
$old->execute([$id]);
$prev = (string) $old->fetchColumn();
if (preg_match('#^img/etiketten/wijn-\d+-\d+\.jpg$#', $prev) && is_file(dirname(__DIR__) . '/' . $prev)) {
    @unlink(dirname(__DIR__) . '/' . $prev);
}

$pdo->prepare('UPDATE wines SET image = ? WHERE id = ?')->execute([$path, $id]);
echo json_encode(['image' => $path]);
