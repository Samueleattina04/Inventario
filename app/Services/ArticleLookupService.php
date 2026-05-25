<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class ArticleLookupService
{
    public function lookup(string $scannedLot): array
    {
        $scannedLot = trim($scannedLot);
        [$id, $type] = $this->parseLot($scannedLot);

        if ($type === 'invalid') {
            return [
                'found'        => false,
                'source'       => 'not_found',
                'article_code' => '',
                'description'  => '',
                'um'           => '',
                'lot'          => $scannedLot,
                'lot_match'    => false,
            ];
        }

        $result = $this->lookupSqlServer($scannedLot, $id);
        if ($result['found']) {
            return $result;
        }

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

    /**
     * Search articles in Access by code or description.
     * Returns up to 40 results from T_INGREDIENTI + T_PRODOTTI.
     */
    public function searchArticles(string $query): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        $results = [];

        try {
            $pdo  = $this->accessPdo();
            $like = '%' . $query . '%';

            $stmt = $pdo->prepare(
                "SELECT [IDIngrediente], [Nome comerc Ingrediente], [CodGestionale], [UM]
                 FROM [T_INGREDIENTI]
                 WHERE [CodGestionale] LIKE ? OR [Nome comerc Ingrediente] LIKE ?
                 ORDER BY [CodGestionale]"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $results[] = [
                    'code'        => $row['CodGestionale'] ?: (string) $row['IDIngrediente'],
                    'description' => $row['Nome comerc Ingrediente'] ?? '',
                    'um_label'    => $this->resolveUm($row['UM']),
                    'type'        => 'ingrediente',
                ];
            }

            $stmt = $pdo->prepare(
                "SELECT [IDProdottoAziendale], [Nome commerciale PA], [CodGestionale], [CodiceAziendale], [unimis]
                 FROM [T_PRODOTTI]
                 WHERE [CodGestionale] LIKE ? OR [CodiceAziendale] LIKE ? OR [Nome commerciale PA] LIKE ?
                 ORDER BY [CodiceAziendale]"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $code = $row['CodGestionale'] ?: ($row['CodiceAziendale'] ?: (string) $row['IDProdottoAziendale']);
                $results[] = [
                    'code'        => $code,
                    'description' => $row['Nome commerciale PA'] ?? '',
                    'um_label'    => $this->resolveUm($row['unimis']),
                    'type'        => 'prodotto',
                ];
            }
        } catch (Throwable $e) {
            Log::error('ArticleSearch error', ['q' => $query, 'error' => $e->getMessage()]);
        }

        return array_slice($results, 0, 40);
    }

    // -------------------------------------------------------------------------

    private function parseLot(string $lot): array
    {
        if (str_contains($lot, '.')) {
            $parts = explode('.', $lot, 2);
            if ($parts[0] !== '' && ($parts[1] ?? '') !== '') {
                return [$parts[0], 'ingrediente'];
            }
            return [$lot, 'invalid'];
        }
        if (str_contains($lot, '-')) {
            $parts = explode('-', $lot, 2);
            if ($parts[0] !== '' && ($parts[1] ?? '') !== '') {
                return [$parts[0], 'prodotto'];
            }
            return [$lot, 'invalid'];
        }
        return [$lot, 'invalid'];
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

            return [
                'found'        => true,
                'source'       => 'sqlsrv',
                'article_code' => $lotRow->CodArt ?? $id,
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
            $pdo = $this->accessPdo();

            if ($type === 'ingrediente') {
                $stmt = $pdo->prepare(
                    'SELECT [IDIngrediente], [Nome comerc Ingrediente], [CodGestionale], [UM]
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
                        'um'           => $this->resolveUm($row['UM']),
                        'lot'          => $fullLot,
                        'lot_match'    => true,
                    ];
                }
            } elseif ($type === 'prodotto') {
                $stmt = $pdo->prepare(
                    'SELECT [IDProdottoAziendale], [Nome commerciale PA], [CodGestionale], [CodiceAziendale], [unimis]
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
                        'um'           => $this->resolveUm($row['unimis']),
                        'lot'          => $fullLot,
                        'lot_match'    => true,
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::error('ArticleLookup Access error', ['lot' => $fullLot, 'id' => $id, 'type' => $type, 'error' => $e->getMessage()]);
        }

        return ['found' => false];
    }

    private function accessPdo(): PDO
    {
        $dsn      = config('database.access_odbc.dsn', '');
        $username = config('database.access_odbc.username', '') ?: null;
        $password = config('database.access_odbc.password', '') ?: null;

        $pdo = new PDO('odbc:' . $dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    /**
     * Map UM integer IDs to unit labels.
     * Values are configurable in config/inventory.php (um_map key).
     */
    private function resolveUm(mixed $umId): string
    {
        $map = config('inventory.um_map', []);
        return $map[(int) $umId] ?? 'PZ';
    }
}
