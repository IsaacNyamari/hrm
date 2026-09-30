<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/hrm-backup-test-'.uniqid();
    // The command only reads this connection's settings to build the mysqldump
    // call; it is never connected to, and the app's own connection is untouched.
    config([
        'backup.path' => $this->dir,
        'backup.connection' => 'backup_probe',
        'database.connections.backup_probe' => ['driver' => 'mysql', 'host' => 'db.invalid', 'port' => 3306, 'database' => 'hrm_probe', 'username' => 'probe', 'password' => 'secret'],
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function fakeMysqldump(string $body): void
{
    Process::fake(function (PendingProcess $process) use ($body) {
        foreach ((array) $process->command as $argument) {
            if (str_starts_with((string) $argument, '--result-file=')) {
                file_put_contents(substr((string) $argument, strlen('--result-file=')), $body);
            }
        }

        return Process::result('');
    });
}

it('writes a compressed, verified dump and removes the plain SQL file', function () {
    fakeMysqldump("-- dump\nCREATE TABLE `users` (id int);\n");

    $this->artisan('backup:database')->assertSuccessful();

    $files = File::glob("{$this->dir}/*");

    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('.sql.gz')
        ->and(gzdecode(file_get_contents($files[0])))->toContain('CREATE TABLE');
});

it('discards a dump that holds no schema and fails', function () {
    fakeMysqldump("-- empty\n");

    $this->artisan('backup:database')->assertFailed();

    expect(File::glob("{$this->dir}/*"))->toBe([]);
});

it('prunes dumps older than the retention window', function () {
    File::ensureDirectoryExists($this->dir);
    $old = "{$this->dir}/old.sql.gz";
    file_put_contents($old, 'x');
    touch($old, now()->subDays(40)->getTimestamp());
    fakeMysqldump("CREATE TABLE t (id int);\n");

    $this->artisan('backup:database', ['--keep' => 14])->assertSuccessful();

    expect(file_exists($old))->toBeFalse()->and(File::glob("{$this->dir}/*"))->toHaveCount(1);
});

it('refuses to run against a non-MySQL connection', function () {
    config(['backup.connection' => null]);

    $this->artisan('backup:database')->assertFailed();
});
