<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendRecoveryCodeTelegramJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-marker-'.uniqid().'.tmp';
        config(['app.initialized_marker_path' => $this->markerPath]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->markerPath)) {
            @unlink($this->markerPath);
        }

        parent::tearDown();
    }

    public function test_onboarding_screen_can_be_rendered_when_first_run(): void
    {
        User::factory()->create();

        $this->get('/mulai')
            ->assertOk()
            ->assertSee('Mulai dari Awal')
            ->assertSee('Pulihkan dari Backup')
            ->assertSee('admin@sekolah.test');
    }

    public function test_login_redirects_to_onboarding_when_first_run(): void
    {
        User::factory()->create();

        $this->get('/login')->assertRedirect(route('onboarding'));
    }

    public function test_onboarding_hidden_when_user_has_logged_in(): void
    {
        User::factory()->create(['last_login_at' => now()]);

        $this->get('/mulai')->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
    }

    public function test_login_shows_form_when_user_has_logged_in(): void
    {
        User::factory()->create(['last_login_at' => now()]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke Akun');
    }

    public function test_recovery_code_can_be_generated_from_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/mulai/recovery-code');

        $response->assertRedirect();
        $response->assertSessionHas('recovery_code');

        $user->refresh();
        $this->assertTrue($user->hasRecoveryCode());
        $this->assertTrue($user->verifyRecoveryCode((string) session('recovery_code')));
    }

    public function test_recovery_code_generation_blocked_after_first_run(): void
    {
        User::factory()->create(['last_login_at' => now()]);

        $this->post('/mulai/recovery-code')->assertRedirect(route('login'));
    }

    public function test_recovery_code_generation_dispatches_telegram_job_when_configured(): void
    {
        Queue::fake();

        User::factory()->create([
            'telegram_chat_id' => '123456789',
            'telegram_bot_token' => 'token123',
        ]);

        $this->post('/mulai/recovery-code')->assertRedirect();

        Queue::assertPushed(SendRecoveryCodeTelegramJob::class);
    }

    public function test_recovery_code_generation_does_not_dispatch_when_not_configured(): void
    {
        Queue::fake();

        User::factory()->create();

        $this->post('/mulai/recovery-code')->assertRedirect();

        Queue::assertNotPushed(SendRecoveryCodeTelegramJob::class);
    }
}
