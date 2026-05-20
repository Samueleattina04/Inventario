<?php
// FILE DI TEST TEMPORANEO - ELIMINARE DOPO L'USO
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    // Consenti solo da localhost o commenta questa riga per test da rete locale
    // die('Accesso negato');
}

echo '<pre>';

// Carica .env manualmente
$env = [];
foreach (file(__DIR__ . '/../.env') as $line) {
    $line = trim($line);
    if (!$line || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $val = trim($val, '"\'');
    $env[trim($key)] = $val;
}

echo "=== VARIABILI CARICATE ===\n";
echo "SQLSRV_HOST: " . ($env['SQLSRV_HOST'] ?? 'NON TROVATO') . "\n";
echo "SQLSRV_DATABASE: " . ($env['SQLSRV_DATABASE'] ?? 'NON TROVATO') . "\n";
echo "SQLSRV_USERNAME: " . ($env['SQLSRV_USERNAME'] ?? 'NON TROVATO') . "\n";
echo "ACCESS_DSN: " . ($env['ACCESS_DSN'] ?? 'NON TROVATO') . "\n\n";

// Test SQL Server
echo "=== TEST SQL SERVER ===\n";
try {
    $host = $env['SQLSRV_HOST'] ?? '';
    $db   = $env['SQLSRV_DATABASE'] ?? '';
    $user = $env['SQLSRV_USERNAME'] ?? '';
    $pass = $env['SQLSRV_PASSWORD'] ?? '';
    $dsn  = "sqlsrv:Server={$host};Database={$db};TrustServerCertificate=1;Encrypt=0";
    $pdo  = new PDO($dsn, $user, $pass);
    echo "Connessione OK\n";

    $stmt = $pdo->query("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Righe MagProgrLotto:\n";
    print_r($rows);
} catch (Throwable $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
}

// Test Access
echo "\n=== TEST ACCESS ===\n";
try {
    $dsn = $env['ACCESS_DSN'] ?? '';
    echo "DSN usato: $dsn\n";
    $pdo = new PDO('odbc:' . $dsn);
    echo "Connessione OK\n";

    $stmt = $pdo->query("SELECT TOP 3 [IDIngrediente], [Nome commerc Ingrediente] FROM [T_INGREDIENTI]");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Righe T_INGREDIENTI:\n";
    print_r($rows);
} catch (Throwable $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
}

echo '</pre>';
