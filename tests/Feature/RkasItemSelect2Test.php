<?php

namespace Tests\Feature;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Models\TransaksiBku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkasItemSelect2Test extends TestCase
{
    use RefreshDatabase;

    public function test_select2_returns_json(): void
    {
        $user = User::factory()->create();
        $program = MasterProgram::factory()->create();
        $rekening = MasterKodeRekening::factory()->create();
        $sd = SumberDana::factory()->create();
        $item = RkasItem::factory()->create([
            'no_urut' => 1,
            'uraian' => 'Belanja ATK',
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'sumber_dana_id' => $sd->id,
        ]);

        $response = $this->actingAs($user)->getJson('/rkas-items/select2?q=ATK');

        $response->assertStatus(200);
        $response->assertJsonStructure(['results' => [['id', 'text']]]);
        $response->assertJsonFragment(['id' => $item->id]);
    }

    public function test_select2_returns_paginated_results(): void
    {
        $user = User::factory()->create();
        $program = MasterProgram::factory()->create();
        $rekening = MasterKodeRekening::factory()->create();
        $sd = SumberDana::factory()->create();
        RkasItem::factory()->count(25)->create([
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'sumber_dana_id' => $sd->id,
        ]);

        $response = $this->actingAs($user)->getJson('/rkas-items/select2?page=1');

        $response->assertStatus(200);
        $response->assertJsonStructure(['results', 'pagination' => ['more']]);
    }

    public function test_select2_returns_empty_for_no_match(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/rkas-items/select2?q=NONEXISTENT');

        $response->assertStatus(200);
        $response->assertJson(['results' => []]);
    }

    public function test_guest_cannot_access_select2(): void
    {
        $response = $this->getJson('/rkas-items/select2');

        $response->assertStatus(401);
    }

    public function test_select2_with_bulan_computes_cumulative_sisa(): void
    {
        $user = User::factory()->create();
        $program = MasterProgram::factory()->create();
        $rekening = MasterKodeRekening::factory()->create();
        $sd = SumberDana::factory()->create();
        $tahun = TahunAnggaran::factory()->create(['status' => true]);

        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'no_urut' => 1,
            'uraian' => 'Belanja ATK',
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'sumber_dana_id' => $sd->id,
            'jumlah' => 1000000,
        ]);

        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 1, 'rencana' => 500000]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 2, 'rencana' => 500000]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'sumber_dana_id' => $sd->id,
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 200000,
        ]);

        // Tanpa bulan: sisa = jumlah tahunan - semua realisasi
        $response = $this->actingAs($user)->getJson('/rkas-items/select2?q=ATK');
        $response->assertStatus(200);
        $yearly = $this->findResult($response, $item->id);
        $this->assertNotNull($yearly);
        $this->assertEqualsWithDelta(800000.0, (float) $yearly['sisa'], 0.001);

        // Dengan bulan=1: sisa = rencana kumulatif s.d. bulan 1 - realisasi s.d. bulan 1
        $response2 = $this->actingAs($user)->getJson('/rkas-items/select2?q=ATK&bulan=1');
        $response2->assertStatus(200);
        $monthly = $this->findResult($response2, $item->id);
        $this->assertNotNull($monthly);
        $this->assertEqualsWithDelta(300000.0, (float) $monthly['sisa'], 0.001);
        $this->assertStringContainsString('Sisa s.d. bulan 1', $monthly['text']);
    }

    /**
     * @param \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> $response
     * @return array<string, mixed>|null
     */
    private function findResult($response, int|string $id): ?array
    {
        $results = $response->json('results');
        if (!is_array($results)) {
            return null;
        }

        foreach ($results as $result) {
            if (is_array($result) && (string) ($result['id'] ?? '') === (string) $id) {
                return $result;
            }
        }

        return null;
    }
}
