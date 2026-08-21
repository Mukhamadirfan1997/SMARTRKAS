<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTimesTest extends TestCase
{
    private function expressionFor(string $commandSubstring): ?string
    {
        $events = $this->app->make(Schedule::class)->events();

        foreach ($events as $event) {
            $command = (string) ($event->command ?? '');

            if (str_contains($command, $commandSubstring)) {
                return $event->expression;
            }
        }

        return null;
    }

    public function test_backup_clean_runs_at_20_00_daily(): void
    {
        $this->assertSame('0 20 * * *', $this->expressionFor('backup:clean'));
    }

    public function test_backup_run_runs_at_20_15_daily(): void
    {
        $this->assertSame('15 20 * * *', $this->expressionFor('backup:run'));
    }

    public function test_audit_clean_runs_sunday_20_30(): void
    {
        $this->assertSame('30 20 * * 0', $this->expressionFor('audit:clean'));
    }

    public function test_kwitansi_clean_runs_monthly_at_20_40(): void
    {
        $this->assertSame('40 20 1 * *', $this->expressionFor('kwitansi:clean'));
    }

    public function test_no_schedule_left_in_the_middle_of_the_night(): void
    {
        $events = $this->app->make(Schedule::class)->events();

        foreach ($events as $event) {
            $expression = $event->expression;

            if (preg_match('/^(\d+) (\d+) /', $expression, $m)) {
                $hour = (int) $m[2];

                if ((int) $m[1] === 0) {
                    $this->assertGreaterThanOrEqual(7, $hour, "Jadwal jam {$expression} tidak masuk akal untuk desktop.");
                }
            }
        }

        $this->assertNotEmpty($events);
    }
}
