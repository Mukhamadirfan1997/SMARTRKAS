<?php

namespace Tests\Unit;

use App\Support\PdoSqliteDumper;
use PHPUnit\Framework\TestCase;

class PdoSqliteDumperTest extends TestCase
{
    public function test_dump_to_file_creates_valid_copy(): void
    {
        $tmpDir = sys_get_temp_dir().'/smartrkas-test-dump-'.uniqid('', true);
        mkdir($tmpDir, 0777, true);

        $dbPath = $tmpDir.'/test.sqlite';
        $dumpPath = $tmpDir.'/dump.sql';

        $pdo = new \PDO('sqlite:'.$dbPath);
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO test (name) VALUES ('hello')");
        $pdo = null;

        $dumper = new PdoSqliteDumper;
        $dumper->setDbName($dbPath);
        $dumper->dumpToFile($dumpPath);

        $this->assertFileExists($dumpPath);
        $this->assertGreaterThan(0, filesize($dumpPath));

        // Verify the copy is a valid SQLite database
        $verify = new \PDO('sqlite:'.$dumpPath);
        $result = $verify->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test'")->fetch();
        $this->assertNotEmpty($result);

        $row = $verify->query("SELECT name FROM test WHERE id=1")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('hello', $row['name']);
        $verify = null;

        @unlink($dbPath);
        @unlink($dumpPath);
        @rmdir($tmpDir);
    }

    public function test_dump_to_file_throws_when_source_missing(): void
    {
        $tmpDir = sys_get_temp_dir().'/smartrkas-test-dump-'.uniqid('', true);
        mkdir($tmpDir, 0777, true);

        $dumper = new PdoSqliteDumper;
        $dumper->setDbName($tmpDir.'/nonexistent.sqlite');

        $this->expectException(\RuntimeException::class);
        $dumper->dumpToFile($tmpDir.'/dump.sql');

        @rmdir($tmpDir);
    }
}
