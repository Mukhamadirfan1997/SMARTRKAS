<?php

namespace Tests\Feature\Support;

use App\Support\DataDirEnv;
use Tests\TestCase;

class DataDirEnvTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-datadir-'.uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.DIRECTORY_SEPARATOR.'.env');
        @rmdir($this->dir);
        putenv('SMARTRKAS_TEST_ENV');
        unset($_ENV['SMARTRKAS_TEST_ENV'], $_SERVER['SMARTRKAS_TEST_ENV']);

        parent::tearDown();
    }

    public function test_loads_env_file_from_data_dir(): void
    {
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'.env', "SMARTRKAS_TEST_ENV=from-data-dir\n");

        DataDirEnv::load($this->dir);

        $this->assertSame('from-data-dir', $_ENV['SMARTRKAS_TEST_ENV'] ?? null);
    }

    public function test_load_overrides_existing_value(): void
    {
        putenv('SMARTRKAS_TEST_ENV=old-value');
        $_ENV['SMARTRKAS_TEST_ENV'] = 'old-value';

        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'.env', "SMARTRKAS_TEST_ENV=new-value\n");

        DataDirEnv::load($this->dir);

        $this->assertSame('new-value', $_ENV['SMARTRKAS_TEST_ENV'] ?? null);
    }

    public function test_load_is_safe_when_file_missing(): void
    {
        DataDirEnv::load($this->dir);

        $this->assertArrayNotHasKey('SMARTRKAS_TEST_ENV', $_ENV);
    }
}
