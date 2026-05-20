<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class ArticleLookupService
{
    /**
     * Lookup an article from the raw scanned lot string.
     *
     * Lot format with '.' → ingredient: "5789.14026.734" → IDIngrediente = "5789" (T_INGREDIENTI)
     * Lot format with '-' → product:    "8055-14026280"  → IDProdottoAziendale = "8055" (T_PRODOTTI)
     *
     * Strategy: SQL Server (MagProgrLotto) first, Access fallback.
     */
    public function lookup(string $scannedLot): array
    {
        $scannedLot = trim($scannedLot);
        [$id, $type] = $this->parseLot($scannedLot);

        // 1. SQL Server
        $result = $this->lookupSqlServer($scannedLot, $id);
        if ($result['found']) {
            return $result;
        }

        // 2. Access fallback
        $result = $this->lookupAccess($scannedLot, $id, $type);
        if ($result['found']) {
            return $result;
        }

        return [
            'found'        => false,
            'source'       => 'not_found',
            'article_code' => $id,
            'description'  => '',
            'um'           => '',
            'lot'          => $scannedLot,
            'lot_match'    => false,
        ];
    }

    // -------------------------------------------------------------------------

    private function parseLot(string $lot): array
    {
        if (str_contains($lot, '.')) {
            return [explode('.', $lot)[0], 'ingrediente'];
        }
        if (str_contains($lot, '-')) {
            return [explode('-', $lot)[0], 'prodotto'];
        }
        return [$lot, 'unknown'];
    }

    private function lookupSqlServer(string $fullLot, string $id): array
    {
        try {
            $lotRow = DB::connection('articles_sqlsrv')
                ->table('MagProgrLotto')
                ->where('RifLottoAlfab', $fullLot)
                ->first();

            if (! $lotRow) {
                return ['found' => false];
            }

            $codArt = $lotRow->CodArt ?? $id;

            return [
                'found'        => true,
                'source'       => 'sqlsrv',
                'article_code' => $codArt,
                'description'  => '',
                'um'           => '',
                'lot'          => $fullLot,
                'lot_match'    => true,
            ];
        } catch (Throwable $e) {
            Log::error('ArticleLookup SQL Server error', ['lot' => $fullLot, 'error' => $e->getMessage()]);
            return ['found' => false];
        }
    }

    private function lookupAccess(string $fullLot, string $id, string $type): array
    {
        try {
            $dsn      = env('ACCESS_DSN', '');
            $username = env('ACCESS_USERNAME', '') ?: null;
            $password = env('ACCESS_PASSWORD', '') ?: null;

            $pdo = new PDO('odbc:' . $dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($type === 'ingrediente') {
                $stmt = $pdo->prepare(
                    'SELECT [IDIngrediente], [Nome comerc Ingrediente], [CodGestionale]
                     FROM [T_INGREDIENTI]
                     WHERE [IDIngrediente] = ?'
                );
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    return [
                        'found'        => true,
                        'source'       => 'access',
                        'article_code' => $row['CodGestionale'] ?? $row['IDIngrediente'] ?? $id,
                        'description'  => $row['Nome comerc Ingrediente'] ?? '',
                        'um'           => '',
                        'lot'          => $fullLot,
                        'lot_match'    => true,
                    ];
                }
            } elseif ($type === 'prodotto') {
                $stmt = $pdo->prepare(
                    'SELECT [IDProdottoAziendale], [Nome commerciale PA], [CodGestionale], [CodiceAziendale]
                     FROM [T_PRODOTTI]
                     WHERE [IDProdottoAziendale] = ?'
                );
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    return [
                        'found'        => true,
                        'source'       => 'access',
                        'article_code' => $row['CodGestionale'] ?: ($row['CodiceAziendale'] ?? $row['IDProdottoAziendale'] ?? $id),
                        'description'  => $row['Nome commerciale PA'] ?? '',
                        'um'           => '',
                        'lot'          => $fullLot,
                        'lot_match'    => true,
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::error('ArticleLookup Access error', ['lot' => $fullLot, 'id' => $id, 'type' => $type, 'dsn' => env('ACCESS_DSN'), 'error' => $e->getMessage()]);
        }

        return ['found' => false];
    }
}
