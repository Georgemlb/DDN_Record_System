<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'ddn:backup {--keep=14 : Number of recent backups to keep}';

    protected $description = 'Create a timestamped database backup in storage/app/backups and prune old backups';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $timestamp = now()->format('Y-m-d_His');

        if ($driver === 'sqlite') {
            $dbPath = config("database.connections.{$connection}.database");
            if (! is_string($dbPath) || ! File::exists($dbPath)) {
                $this->error('SQLite database file not found.');

                return self::FAILURE;
            }

            $target = "{$backupDir}/ddn_sqlite_{$timestamp}.sqlite";
            File::copy($dbPath, $target);
            $this->info("SQLite backup saved to: {$target}");
        } elseif ($driver === 'pgsql') {
            $target = "{$backupDir}/ddn_pgsql_{$timestamp}.sql";
            $url = config("database.connections.{$connection}.url");
            $cmd = is_string($url) && $url !== ''
                ? ['pg_dump', $url, '-f', $target]
                : [
                    'pg_dump',
                    '-h', (string) config("database.connections.{$connection}.host"),
                    '-p', (string) config("database.connections.{$connection}.port", 5432),
                    '-U', (string) config("database.connections.{$connection}.username"),
                    '-d', (string) config("database.connections.{$connection}.database"),
                    '-f', $target,
                ];

            $result = Process::env([
                'PGPASSWORD' => (string) config("database.connections.{$connection}.password"),
            ])->run($cmd);

            if (! $result->successful()) {
                $this->error('pg_dump failed: '.$result->errorOutput());

                return self::FAILURE;
            }

            $this->info("PostgreSQL backup saved to: {$target}");
        } elseif ($driver === 'mysql') {
            $target = "{$backupDir}/ddn_mysql_{$timestamp}.sql";
            $result = Process::env([
                'MYSQL_PWD' => (string) config("database.connections.{$connection}.password"),
            ])->run([
                'mysqldump',
                '-h', (string) config("database.connections.{$connection}.host"),
                '-P', (string) config("database.connections.{$connection}.port", 3306),
                '-u', (string) config("database.connections.{$connection}.username"),
                (string) config("database.connections.{$connection}.database"),
                "--result-file={$target}",
            ]);

            if (! $result->successful()) {
                $this->error('mysqldump failed: '.$result->errorOutput());

                return self::FAILURE;
            }

            $this->info("MySQL backup saved to: {$target}");
        } else {
            $this->error("Unsupported database driver: {$driver}");

            return self::FAILURE;
        }

        $keep = max(1, (int) $this->option('keep'));
        $files = collect(File::files($backupDir))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        foreach ($files->slice($keep) as $oldFile) {
            File::delete($oldFile->getPathname());
        }

        return self::SUCCESS;
    }
}
