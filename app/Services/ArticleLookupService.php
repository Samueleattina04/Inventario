<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class ArticleLookupService
{
    public function lookup(string $articleCode, string $lot): array
    {
        $empty = [
            'found'        => false,
            'source'       => 'not_found',
            'article_code' => $articleCode,
            'description'  => '',
            'um'           => '',
            'lot'          => $lot,
        ];

        // --- SQL Server attempt ---
        try {
            $table   = env('SQLSRV_TABLE_ARTICLES', 'articles');
            $colCode = env('SQLSRV_COL_CODE', 'code');
            $colDesc = env('SQLSRV_COL_DESC', 'description');
            $colUm   = env('SQLSRV_COL_UM', 'um');
            $colLot  = env('SQLSRV_COL_LOT', 'lot');

            $row = DB::connection('articles_sqlsrv')
                ->table($table)
                ->where($colCode, $articleCode)
                ->where($colLot, $lot)
                ->first();

            if ($row) {
                $rowArr = (array) $row;
                return [
                    'found'        => true,
                    'source'       => 'sqlsrv',
                    'article_code' => $rowArr[$colCode] ?? $articleCode,
                    'description'  => $rowArr[$colDesc] ?? '',
                    'um'           => $rowArr[$colUm]   ?? '',
                    'lot'          => $rowArr[$colLot]  ?? $lot,
                ];
            }
        } catch (Throwable) {
            // SQL Server not reachable in dev – fall through to Access
        }

        // --- Access / ODBC fallback ---
        try {
            $table   = env('ACCESS_TABLE_ARTICLES', 'articles');
            $colCode = env('ACCESS_COL_CODE', 'code');
            $colDesc = env('ACCESS_COL_DESC', 'description');
            $colUm   = env('ACCESS_COL_UM', 'um');
            $colLot  = env('ACCESS_COL_LOT', 'lot');

            $row = DB::connection('articles_access')
                ->table($table)
                ->where($colCode, $articleCode)
                ->where($colLot, $lot)
                ->first();

            if ($row) {
                $rowArr = (array) $row;
                return [
                    'found'        => true,
                    'source'       => 'access',
                    'article_code' => $rowArr[$colCode] ?? $articleCode,
                    'description'  => $rowArr[$colDesc] ?? '',
                    'um'           => $rowArr[$colUm]   ?? '',
                    'lot'          => $rowArr[$colLot]  ?? $lot,
                ];
            }
        } catch (Throwable) {
            // Access not reachable in dev – return not_found
        }

        return $empty;
    }
}
