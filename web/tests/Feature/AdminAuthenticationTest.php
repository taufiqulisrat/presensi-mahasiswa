<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_homepage_renders_for_students_without_a_login(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Tanpa login mahasiswa');
    }

    public function test_unauthenticated_admin_dashboard_request_redirects_to_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_login_redirects_to_dashboard_with_valid_credentials(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'is_admin' => true,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'password',
        ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_regular_user_cannot_login_to_admin_dashboard(): void
    {
        User::factory()->create(['email' => 'student@example.test', 'is_admin' => false]);

        $this->post(route('admin.login.store'), [
            'email' => 'student@example.test',
            'password' => 'password',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi admin tidak sesuai.']);

        $this->assertGuest();
    }

    public function test_authenticated_regular_user_receives_a_403_from_admin_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_dashboard_renders_for_an_authenticated_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan presensi');
    }

    public function test_admin_can_view_the_face_enrollment_screen_with_a_completion_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.students.enrollment.create', $student))
            ->assertOk()
            ->assertSee('data-complete-url="'.route('admin.students.index').'"', false)
            ->assertSee('data-enrollment-complete="false"', false);
    }

    public function test_admin_can_update_their_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.password.update'), [
                'current_password' => 'password',
                'password' => 'kata-sandi-baru',
                'password_confirmation' => 'kata-sandi-baru',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Hash::check('kata-sandi-baru', $admin->fresh()->password));
    }
}
