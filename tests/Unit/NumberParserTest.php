<?php

namespace Tests\Unit;

use App\Support\NumberParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumberParserTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function rupiahProvider(): array
    {
        return [
            'indonesian thousands' => ['1.500.000', '1500000'],
            'indonesian thousands with decimal' => ['1.500.000,50', '1500000.50'],
            'indonesian decimal only' => ['1500000,50', '1500000.50'],
            'plain integer' => ['1500000', '1500000'],
            'already valid decimal (calculator output)' => ['1000000.00', '1000000.00'],
            'already valid decimal one digit' => ['250000.5', '250000.5'],
            'single dot thousands' => ['2.500', '2500'],
            'single dot thousands three digits' => ['1.000', '1000'],
            'with space' => ['1 500 000', '1500000'],
            'spaces and comma' => ['1 500 000,5', '1500000.5'],
            'empty string' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('rupiahProvider')]
    public function test_rupiah(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, NumberParser::rupiah($input));
    }

    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function decimalProvider(): array
    {
        return [
            'indonesian decimal' => ['2,5', '2.5'],
            'dot decimal kept' => ['2.5', '2.5'],
            'integer' => ['10', '10'],
            'spaces' => ['2 5', '25'],
            'empty string' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('decimalProvider')]
    public function test_decimal(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, NumberParser::decimal($input));
    }
}
