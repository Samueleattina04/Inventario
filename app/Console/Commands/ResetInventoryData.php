<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\InventoryRecord;
use App\Models\User;
use Illuminate\Console\Command;

class ResetInventoryData extends Command
{
    protected $signature = 'inventory:reset {--force : Skip confirmation}';
    protected $description = 'Delete all inventory records, activity logs and non-admin users';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('Questo eliminerà TUTTE le registrazioni, i log e gli utenti non-admin. Continuare?')) {
                $this->info('Operazione annullata.');
                return self::SUCCESS;
            }
        }

        $records = InventoryRecord::count();
        $logs    = ActivityLog::count();
        $users   = User::where('role', '!=', 'admin')->count();

        ActivityLog::truncate();
        InventoryRecord::truncate();
        User::where('role', '!=', 'admin')->delete();

        $this->info("Eliminati {$records} registrazioni inventario.");
        $this->info("Eliminati {$logs} log attività.");
        $this->info("Eliminati {$users} utenti non-admin.");
        $this->newLine();

        $admin = User::where('role', 'admin')->first();
        $this->info("Utenti rimasti: " . User::count());
        if ($admin) {
            $this->line("  → {$admin->name} ({$admin->username}) - admin");
        }

        return self::SUCCESS;
    }
}
