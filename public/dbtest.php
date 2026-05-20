<?php
// FILE DI TEST TEMPORANEO - ELIMINARE DOPO L'USO
echo '<pre>';

$uncPath = '\\\\192.168.3.208\\omni\\OMNITRACK1.3_be.accdb';
$dsn = 'Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $uncPath . ';';

try {
    $pdo = new PDO('odbc:' . $dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connessione Access OK\n\n";

    // Colonne di T_INGREDIENTI
    echo "=== COLONNE T_INGREDIENTI ===\n";
    $stmt = $pdo->query("SELECT * FROM [T_INGREDIENTI] WHERE 1=0");
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $col = $stmt->getColumnMeta($i);
        echo "  [{$col['name']}]\n";
    }

    // Prima riga di T_INGREDIENTI
    echo "\n=== PRIMA RIGA T_INGREDIENTI ===\n";
    $stmt = $pdo->query("SELECT TOP 1 * FROM [T_INGREDIENTI]");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);

    // Colonne di T_PRODOTTI
    echo "\n=== COLONNE T_PRODOTTI ===\n";
    $stmt = $pdo->query("SELECT * FROM [T_PRODOTTI] WHERE 1=0");
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $col = $stmt->getColumnMeta($i);
        echo "  [{$col['name']}]\n";
    }

    // Prima riga di T_PRODOTTI
    echo "\n=== PRIMA RIGA T_PRODOTTI ===\n";
    $stmt = $pdo->query("SELECT TOP 1 * FROM [T_PRODOTTI]");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);

} catch (Throwable $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
}

echo '</pre>';
