<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function docFor(User $user, array $attrs = []): Document
    {
        return Document::factory()->create(array_merge(['user_id' => $user->id], $attrs));
    }

    public function test_other_users_cannot_access_a_document(): void
    {
        Storage::fake('local');
        $a = User::factory()->create();
        $b = User::factory()->create();
        $doc = $this->docFor($b);
        Storage::disk('local')->put($doc->file_path, '%PDF-1.4');

        $this->actingAs($a);
        $this->get(route('documents.view', $doc->uuid))->assertNotFound();
        $this->get(route('documents.download', [$doc->uuid, 'original']))->assertNotFound();
        $this->post(route('documents.delete', $doc->uuid))->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $doc->id]);
    }

    public function test_owner_can_view_document(): void
    {
        $a = User::factory()->create();
        $doc = $this->docFor($a);

        $this->actingAs($a)->get(route('documents.view', $doc->uuid))->assertOk();
    }

    public function test_admin_can_view_any_document(): void
    {
        $b = User::factory()->create();
        $doc = $this->docFor($b);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('documents.view', $doc->uuid))
            ->assertOk();
    }

    public function test_customers_index_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('customers.index'))
            ->assertOk();
    }

    public function test_dashboard_data_only_lists_own_documents(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $mine = $this->docFor($a);
        $theirs = $this->docFor($b);

        $uuids = collect($this->actingAs($a)->getJson(route('dashboard.data'))
            ->assertOk()
            ->json('documents'))->pluck('uuid');

        $this->assertTrue($uuids->contains($mine->uuid));
        $this->assertFalse($uuids->contains($theirs->uuid));
    }

    public function test_admin_login_rejects_non_admin(): void
    {
        $user = User::factory()->create();

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_login_accepts_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }
}
