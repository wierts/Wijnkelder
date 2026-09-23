<?php
header('Content-Type: application/json');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'config.php ontbreekt. Kopieer config.example.php naar config.php en vul je databasegegevens in.']);
    exit;
}
require $configFile;

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kan niet verbinden met database: ' . $e->getMessage()]);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS wines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    grape VARCHAR(255) DEFAULT '',
    region VARCHAR(255) DEFAULT '',
    year INT NULL,
    status VARCHAR(20) DEFAULT 'wait',
    window_note VARCHAR(255) DEFAULT '',
    character_note TEXT,
    serving_json TEXT,
    drunk TINYINT(1) DEFAULT 0,
    qty INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Kolommen die later zijn toegevoegd. Bestaat een kolom al, dan gooit MySQL een
// fout die we bewust negeren; zo blijft dit script veilig om bij elke request te draaien.
$extraColumns = [
    "ALTER TABLE wines ADD COLUMN qty INT DEFAULT 1",
    "ALTER TABLE wines ADD COLUMN producer VARCHAR(255) DEFAULT ''",
    // Nieuw: kleur (rood / wit / rose / mousserend / dessert), landcode (ISO, bv. FR) en kelderlocatie (bv. B-04)
    "ALTER TABLE wines ADD COLUMN color VARCHAR(20) DEFAULT ''",
    "ALTER TABLE wines ADD COLUMN country VARCHAR(2) DEFAULT ''",
    "ALTER TABLE wines ADD COLUMN location VARCHAR(50) DEFAULT ''",
];
foreach ($extraColumns as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // kolom bestaat al, niets doen
    }
}

$pdo->exec("CREATE TABLE IF NOT EXISTS producers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    region VARCHAR(255) DEFAULT '',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
