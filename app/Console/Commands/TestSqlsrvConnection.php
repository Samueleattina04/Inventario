<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestSqlsrvConnection extends Command
{
    protected $signature = 'sqlsrv:test';
    protected $description = 'Test the SQL Server (articles_sqlsrv) connection';

    public function handle(): int
    {
        $this->info('Testing SQL Server connection...');
        $this->line('Host:     ' . env('SQLSRV_HOST'));
        $this->line('Port:     ' . (env('SQLSRV_PORT') ?: '(default 1433)'));
        $this->line('Database: ' . env('SQLSRV_DATABASE'));
        $this->line('User:     ' . env('SQLSRV_USERNAME'));
        $this->newLine();

        try {
            $result = DB::connection('articles_sqlsrv')->selectOne('SELECT GETDATE() AS now, @@SERVERNAME AS server, DB_NAME() AS db');
            $this->info('Connection OK');
            $this->line('Server:   ' . $result->server);
            $this->line('Database: ' . $result->db);
            $this->line('Time:     ' . $result->now);
        } catch (\Throwable $e) {
            $this->error('Connection FAILED');
            $this->line($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Testing ArtAnagrafica table...');
        try {
            $count = DB::connection('articles_sqlsrv')->table('ArtAnagrafica')->count();
            $this->line("ArtAnagrafica rows: {$count}");

            $sample = DB::connection('articles_sqlsrv')
                ->table('ArtAnagrafica')
                ->select('CodArt', 'DesArt', 'MagUm')
                ->first();

            if ($sample) {
                $this->line("Sample: [{$sample->CodArt}] {$sample->DesArt} ({$sample->MagUm})");
            }
        } catch (\Throwable $e) {
            $this->error('ArtAnagrafica query failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All checks passed.');
        return self::SUCCESS;
    }
}
