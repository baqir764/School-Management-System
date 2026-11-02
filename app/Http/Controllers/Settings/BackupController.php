<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ResetDatabaseRequest;
use App\Http\Requests\Settings\RestoreBackupRequest;
use App\Services\AuditLogger;
use FilesystemIterator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use mysqli;
use mysqli_sql_exception;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * One-click manual backup (master spec Phase 6-7 "backups" item - see
 * PROGRESS.md, "## Phase 6-7 - not started"). Deliberately NOT scheduled/
 * automatic - the user chose a manual one-click download.
 *
 * No new Composer dependency: PROGRESS.md's "Known follow-ups" already
 * notes dompdf/maatwebsite-excel were left out of composer.json on
 * purpose, and this feature was built in a sandbox with no shell access
 * to the target machine to run `composer require` even if a package were
 * wanted. So the dump below is hand-rolled over the raw PDO connection
 * (SHOW CREATE TABLE + batched INSERTs, mirroring what `mysqldump`
 * produces) rather than shelling out to the `mysqldump` binary (not
 * guaranteed to be on PATH, or callable from PHP at all, on shared
 * hosting) or adding a backup package. The plain .sql file plus every
 * uploaded file are bundled into one ZIP with PHP's built-in ZipArchive
 * (no extension install needed - it ships with the default PHP build).
 *
 * Also owns the two "danger zone" actions added alongside it -
 * restoreBackup() (upload a ZIP made by download() and replace the
 * current database/files with it) and resetDatabase() (wipe every
 * school-data table back to empty while keeping accounts/roles/settings/
 * audit trail intact). Both are irreversible, both are gated behind the
 * `settings.restore` permission (Super Administrator only - see
 * RoleAndPermissionSeeder), and both require their FormRequest's typed
 * confirmation word plus the acting user's current password.
 */
class BackupController extends Controller
{
    /**
     * Tables this app's own migrations create for framework/security
     * infrastructure or account/access data - kept out of resetDatabase()'s
     * wipe even though that reset is scoped to "school data only" (see
     * resetDatabase()). Deliberately a short *keep* list rather than a
     * long *wipe* list of every school table: a future migration that
     * adds a new school-data table is wiped by default, which is the
     * safer failure mode here.
     */
    private const PROTECTED_TABLES = [
        'migrations',
        'users', 'password_reset_tokens', 'sessions',
        'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
        'roles', 'permissions', 'model_has_permissions', 'model_has_roles', 'role_has_permissions',
        'personal_access_tokens',
        'settings',
        'audit_logs',
    ];

    public function download(): BinaryFileResponse
    {
        abort_unless(request()->user()->can('settings.manage'), 403);

        // A full dump + every uploaded file can take a while on a large
        // school; this route is an intentionally rare, manual action, so
        // it is worth uncapping the request lifetime for it specifically
        // rather than raising the site-wide default.
        set_time_limit(0);

        $timestamp = now()->format('Y-m-d_His');
        $workDir = storage_path('app/backups');
        if (! is_dir($workDir)) {
            mkdir($workDir, 0755, true);
        }

        $sqlPath = $workDir.'/db-'.$timestamp.'.sql';
        $zipPath = $workDir.'/school-backup-'.$timestamp.'.zip';

        try {
            $this->dumpDatabase($sqlPath);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                abort(500, 'Could not create the backup archive.');
            }

            $zip->addFile($sqlPath, 'database.sql');

            // Every disk that can hold user-uploaded data (see
            // config/filesystems.php): logos/photos/id-cards etc. on
            // 'public', and sensitive student/staff documents on
            // 'secure_documents'. 'local' (private, app-internal) and
            // 'backups' (this feature's own scratch space) are
            // deliberately excluded.
            $this->addDirectoryToZip($zip, storage_path('app/public'), 'files/public');
            $this->addDirectoryToZip($zip, storage_path('app/secure_documents'), 'files/secure_documents');

            $zip->close();
        } finally {
            if (is_file($sqlPath)) {
                unlink($sqlPath);
            }
        }

        AuditLogger::log('download', 'backup', null, null, ['filename' => basename($zipPath)]);

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }

    /**
     * Wipes every school-data table (attendance, students, fees, exams,
     * finance, etc.) while leaving user accounts, roles/permissions,
     * school settings and the audit trail untouched (PROTECTED_TABLES
     * above) - so nobody is locked out of their own login and the reset
     * itself still lands in the audit trail. Meant for handing a
     * freshly-reset system to a new school/new academic cycle without
     * re-registering every login. TRUNCATE isn't transactional in MySQL/
     * InnoDB (it's DDL - each one auto-commits), so this cannot be
     * all-or-nothing; FK checks are disabled for the whole loop and
     * always re-enabled via finally, even if one TRUNCATE fails midway.
     */
    public function resetDatabase(ResetDatabaseRequest $request): RedirectResponse
    {
        set_time_limit(0);

        $pdo = DB::connection()->getPdo();

        $allTables = array_map('current', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));
        $tablesToWipe = array_values(array_diff($allTables, self::PROTECTED_TABLES));

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tablesToWipe as $table) {
                $pdo->exec('TRUNCATE TABLE `'.str_replace('`', '``', $table).'`');
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        AuditLogger::log('reset', 'database', null, null, ['tables_wiped' => $tablesToWipe]);

        return redirect()->route('settings.edit')->with('status', __('app.database_reset_done'));
    }

    /**
     * Replaces the current database and uploaded files with the contents
     * of a ZIP previously produced by download() above - the exact
     * reverse operation. The SQL file is executed with
     * mysqli::multi_query() rather than naively splitting the dump on
     * ";\n": dumpDatabase() quotes every value through PDO::quote(),
     * which can legally produce a quoted string containing a literal
     * ";\n" sequence (e.g. a multi-line remarks field), so a plain
     * string-split risks cutting a statement in half. mysqli's
     * multi-statement execution lets the real MySQL parser find
     * statement boundaries instead - this is why it needs its own
     * mysqli connection rather than going through Eloquent/PDO, which
     * Laravel does not configure for multi-statement execution.
     */
    public function restoreBackup(RestoreBackupRequest $request): RedirectResponse
    {
        if (! extension_loaded('mysqli')) {
            abort(500, 'The mysqli PHP extension is required to restore a backup, and is not enabled on this server.');
        }

        set_time_limit(0);

        $workDir = storage_path('app/backups');
        if (! is_dir($workDir)) {
            mkdir($workDir, 0755, true);
        }

        $uploadedFile = $request->file('backup_file');
        $tempZipName = 'restore-upload-'.now()->format('Y-m-d_His').'.zip';
        $uploadedFile->move($workDir, $tempZipName);
        $zipPath = $workDir.'/'.$tempZipName;

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                abort(422, 'Could not open the uploaded file - it does not look like a valid ZIP archive.');
            }

            $sqlContent = $zip->getFromName('database.sql');
            if ($sqlContent === false) {
                $zip->close();
                abort(422, 'The uploaded file does not look like a backup made by this system (database.sql not found inside it).');
            }

            $this->restoreDatabaseDump($sqlContent);

            $this->extractZipEntriesUnderPrefix($zip, 'files/public/', storage_path('app/public'));
            $this->extractZipEntriesUnderPrefix($zip, 'files/secure_documents/', storage_path('app/secure_documents'));

            $zip->close();
        } finally {
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }

        AuditLogger::log('restore', 'backup', null, null, ['filename' => $uploadedFile->getClientOriginalName()]);

        return redirect()->route('settings.edit')->with('status', __('app.backup_restored'));
    }

    /**
     * Runs a full multi-statement SQL dump (DROP/CREATE/INSERT for every
     * table) over its own dedicated mysqli connection - see
     * restoreBackup()'s doc comment for why mysqli rather than PDO. PHP
     * 8.1+'s default mysqli error mode throws mysqli_sql_exception on
     * failure (including partway through multi_query's result stream),
     * so a single try/catch around the whole run is enough to catch
     * a connection failure or a bad statement anywhere in the dump.
     */
    private function restoreDatabaseDump(string $sql): void
    {
        $config = config('database.connections.'.config('database.default'));

        try {
            $mysqli = new mysqli(
                $config['host'] ?? '127.0.0.1',
                $config['username'] ?? '',
                $config['password'] ?? '',
                $config['database'] ?? '',
                (int) ($config['port'] ?? 3306)
            );
            $mysqli->set_charset('utf8mb4');

            $mysqli->multi_query($sql);
            do {
                $result = $mysqli->store_result();
                if ($result) {
                    $result->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());

            $mysqli->close();
        } catch (mysqli_sql_exception $e) {
            abort(500, 'Restore failed - the database may now be in a mixed state. Error: '.$e->getMessage());
        }

        // The Eloquent connection's own PDO handle may hold state (e.g. a
        // prepared statement referencing a column the restore just
        // changed) from before this raw mysqli connection ran - force
        // Laravel to reconnect cleanly on the very next query.
        DB::purge(config('database.default'));
    }

    /**
     * Overlay-restores every file inside the zip whose path starts with
     * $prefix into $destination, flattening that prefix away and
     * recreating subdirectories as needed. This overlays onto whatever is
     * already in $destination - a file that exists on disk but isn't in
     * the backup is left alone, never deleted - so a restore can't wipe
     * something uploaded after the backup was taken.
     */
    private function extractZipEntriesUnderPrefix(ZipArchive $zip, string $prefix, string $destination): void
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || ! str_starts_with($name, $prefix) || str_ends_with($name, '/')) {
                continue;
            }

            $relative = substr($name, strlen($prefix));
            if ($relative === '') {
                continue;
            }

            $targetPath = $destination.'/'.$relative;
            $targetDir = dirname($targetPath);
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $contents = $zip->getFromIndex($i);
            if ($contents !== false) {
                file_put_contents($targetPath, $contents);
            }
        }
    }

    /**
     * Hand-rolled structure+data dump of every table in the current
     * connection, streamed straight to $path row-chunk by row-chunk (never
     * loading a whole table into memory at once). Uses the raw PDO handle
     * rather than Schema/Eloquent helpers so it works uniformly across
     * every table regardless of whether a model exists for it (e.g. the
     * `sessions`/`cache`/`jobs` tables Laravel itself creates), and quotes
     * every value through PDO::quote() so the output is a plain, portable
     * .sql file - any MySQL client can restore it directly, no vendor
     * dump/restore tool required.
     */
    private function dumpDatabase(string $path): void
    {
        $pdo = DB::connection()->getPdo();
        $database = DB::connection()->getDatabaseName();

        $handle = fopen($path, 'w');
        if ($handle === false) {
            abort(500, 'Could not write the backup file.');
        }

        fwrite($handle, "-- School Management System backup\n");
        fwrite($handle, '-- Database: '.$database."\n");
        fwrite($handle, '-- Generated: '.now()->toDateTimeString()."\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET NAMES utf8mb4;\n\n");

        $tables = array_map('current', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));

        foreach ($tables as $table) {
            $quotedTable = '`'.str_replace('`', '``', $table).'`';

            $createRow = $pdo->query('SHOW CREATE TABLE '.$quotedTable)->fetch(PDO::FETCH_ASSOC);
            $createSql = $createRow['Create Table'] ?? null;

            fwrite($handle, "--\n-- Table: {$table}\n--\n\n");
            fwrite($handle, 'DROP TABLE IF EXISTS '.$quotedTable.";\n");
            if ($createSql) {
                fwrite($handle, $createSql.";\n\n");
            }

            $rowCount = (int) $pdo->query('SELECT COUNT(*) FROM '.$quotedTable)->fetchColumn();
            if ($rowCount === 0) {
                continue;
            }

            $columns = null;
            $chunkSize = 200;
            $offset = 0;

            while ($offset < $rowCount) {
                $stmt = $pdo->query('SELECT * FROM '.$quotedTable.' LIMIT '.$chunkSize.' OFFSET '.$offset);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (! $rows) {
                    break;
                }

                if ($columns === null) {
                    $columns = array_keys($rows[0]);
                }

                $columnList = implode(', ', array_map(
                    fn ($column) => '`'.str_replace('`', '``', $column).'`',
                    $columns
                ));

                $valueGroups = [];
                foreach ($rows as $row) {
                    $values = array_map(
                        fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                        $row
                    );
                    $valueGroups[] = '('.implode(', ', $values).')';
                }

                fwrite($handle, 'INSERT INTO '.$quotedTable.' ('.$columnList.") VALUES\n".implode(",\n", $valueGroups).";\n");

                $offset += $chunkSize;
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");

        fclose($handle);
    }

    /**
     * Recursively adds every file under $sourceDir into the zip beneath
     * $zipSubPath, silently doing nothing if the directory doesn't exist
     * yet (a fresh install with no uploads/documents).
     */
    private function addDirectoryToZip(ZipArchive $zip, string $sourceDir, string $zipSubPath): void
    {
        if (! is_dir($sourceDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getPathname();
            $relativePath = $zipSubPath.'/'.ltrim(substr($filePath, strlen($sourceDir)), '/\\');
            $relativePath = str_replace('\\', '/', $relativePath);

            $zip->addFile($filePath, $relativePath);
        }
    }
}
