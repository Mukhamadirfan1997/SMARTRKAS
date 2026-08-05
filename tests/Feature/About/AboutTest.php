<?php

namespace Tests\Feature\About;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AboutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['last_login_at' => now()]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/tentang')->assertRedirect('/login');
    }

    public function test_about_page_shows_version_and_author(): void
    {
        Http::fake([
            'https://api.github.com/*' => Http::response($this->release('v0.1.0'), 200),
        ]);

        $this->actingAs($this->user)
            ->get('/tentang')
            ->assertOk()
            ->assertSee('v0.1.0')
            ->assertSee('IrfanDev97')
            ->assertSee('irfandev97.my.id')
            ->assertSee('github.com/Mukhamadirfan1997/SMARTRKAS');
    }

    public function test_about_shows_update_available_banner_with_backup_warning(): void
    {
        Http::fake([
            'https://api.github.com/*' => Http::response($this->release('v9.9.9'), 200),
        ]);

        $this->actingAs($this->user)
            ->get('/tentang')
            ->assertOk()
            ->assertSee('Versi baru tersedia')
            ->assertSee('Backup data dulu')
            ->assertSee('Backup & Pemulihan');
    }

    public function test_about_shows_up_to_date_when_no_update(): void
    {
        Http::fake([
            'https://api.github.com/*' => Http::response($this->release('v0.1.0'), 200),
        ]);

        $this->actingAs($this->user)
            ->get('/tentang')
            ->assertOk()
            ->assertSee('Sudah versi terbaru')
            ->assertDontSee('Versi baru tersedia');
    }

    public function test_about_shows_offline_state_when_release_check_fails(): void
    {
        Http::fake([
            'https://api.github.com/*' => Http::response([], 500),
        ]);

        $this->actingAs($this->user)
            ->get('/tentang')
            ->assertOk()
            ->assertSee('Tidak dapat memeriksa');
    }

    public function test_check_route_refreshes_update_status(): void
    {
        Http::fake([
            'https://api.github.com/*' => Http::sequence()
                ->push($this->release('v9.9.9'), 200)
                ->push($this->release('v0.1.0'), 200),
        ]);

        $this->actingAs($this->user)->get('/tentang')->assertSee('Versi baru tersedia');
        $this->actingAs($this->user)->get('/tentang/cek-pembaruan')->assertRedirect();
        $this->actingAs($this->user)->get('/tentang')->assertSee('Sudah versi terbaru');
    }

    /**
     * @return array<string, string>
     */
    private function release(string $tag): array
    {
        return [
            'tag_name' => $tag,
            'name' => 'SmartRKAS '.$tag,
            'html_url' => 'https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/'.$tag,
            'published_at' => '2026-08-05T00:00:00Z',
            'body' => 'Catatan rilis.',
        ];
    }
}
