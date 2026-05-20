<?php
// FILE DI TEST TEMPORANEO - ELIMINARE DOPO L'USO
echo '<pre>';

// Test accesso file UNC diretto
echo "=== TEST FILE ACCESS UNC ===\n";
$uncPath = '\\\\192.168.3.208\\omni\\OMNITRACK1.3_be.accdb';
echo "Percorso: $uncPath\n";
echo "File esiste: " . (file_exists($uncPath) ? "SI" : "NO") . "\n\n";

// Test Access ODBC con percorso corretto
echo "=== TEST ACCESS ODBC ===\n";
$dsn = 'Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $uncPath . ';';
echo "DSN: $dsn\n";
try {
    $pdo = new PDO('odbc:' . $dsn);
    echo "Connessione OK\n";
    $stmt = $pdo->query("SELECT TOP 3 [IDIngrediente], [Nome commerc Ingrediente] FROM [T_INGREDIENTI]");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Righe T_INGREDIENTI:\n";
    print_r($rows);
} catch (Throwable $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
}

// Test SQL Server con hostname
echo "\n=== TEST SQL SERVER (hostname) ===\n";
try {
    $pdo = new PDO("sqlsrv:Server=SERVER2019\\SISTEMI;Database=ESOLVER;TrustServerCertificate=1;Encrypt=0", 'ricercalotti', 'RicercaLotti2024!');
    echo "Connessione OK\n";
    $stmt = $pdo->query("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
}

// Test SQL Server con IP (inserisci l'IP reale di SERVER2019)
echo "\n=== TEST SQL SERVER (IP - modifica con IP reale) ===\n";
echo "Inserisci l'IP di SERVER2019 nel file per testare con IP diretto\n";
// $ip = '192.168.X.X'; // <-- decommentare e inserire IP reale
// try {
//     $pdo = new PDO("sqlsrv:Server=$ip\\SISTEMI;Database=ESOLVER;TrustServerCertificate=1;Encrypt=0", 'ricercalotti', 'RicercaLotti2024!');
//     echo "Connessione OK\n";
// } catch (Throwable $e) {
//     echo "ERRORE: " . $e->getMessage() . "\n";
// }

echo '</pre>';
