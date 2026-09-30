<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Nightly compressed MySQL dump with retention. The dump is verified before it
 * is kept (non-empty, contains schema), and old dumps beyond the retention
 * window are pruned. Restore drills are an operational task: see the retention
 * and verification notes in config/backup.php.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep= : Days of dumps to keep (default from config)}';

    protected $description = 'Dump the database to a compressed file and prune old dumps';

    public function handle(): int
    {
        $connection = (array) config('database.connections.'.config('backup.connection', config('database.default')));

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('Database backups support MySQL/MariaDB only.');

            return self::FAILURE;
        }

        $directory = (string) config('backup.path');
        File::ensureDirectoryExists($directory);

        $name = sprintf('%s-%s', $connection['database'], now()->format('Ymd-His'));
        $sqlPath = "{$directory}/{$name}.sql";

        $result = Process::timeout((int) config('backup.timeout', 1800))
            ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->run([
                (string) config('backup.mysqldump_binary', 'mysqldump'),
                '--host='.($connection['host'] ?? '127.0.0.1'),
                '--port='.($connection['port'] ?? 3306),
                '--user='.($connection['username'] ?? 'root'),
                '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
                '--result-file='.$sqlPath,
                (string) $connection['database'],
            ]);

        if (! $result->successful() || ! File::exists($sqlPath) || File::size($sqlPath) === 0) {
            File::delete($sqlPath);
            $this->error('mysqldump failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        if (! str_contains((string) file_get_contents($sqlPath, false, null, 0, 65536), 'CREATE TABLE')) {
            File::delete($sqlPath);
            $this->error('The dump contains no schema; it was discarded.');

            return self::FAILURE;
        }

        $gzPath = "{$sqlPath}.gz";
        $this->compress($sqlPath, $gzPath);
        File::delete($sqlPath);

        $pruned = $this->prune($directory, (int) ($this->option('keep') ?: config('backup.retention_days', 14)));

        $this->info(sprintf('Backup written to %s (%s KB); pruned %d old file(s).', basename($gzPath), number_format(File::size($gzPath) / 1024, 1), $pruned));

        return self::SUCCESS;
    }

    private function compress(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($destination, 'wb9');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
    }

    private function prune(string $directory, int $days): int
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $count = 0;

        foreach (File::glob("{$directory}/*.sql.gz") ?: [] as $file) {
            if (File::lastModified($file) < $cutoff) {
                File::delete($file);
                $count++;
            }
        }

        return $count;
    }
}
