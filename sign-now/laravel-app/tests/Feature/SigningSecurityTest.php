<?php

namespace Tests\Feature;

use App\Models\AdminField;
use App\Models\Document;
use App\Models\Field;
use App\Models\Recipient;
use App\Models\User;
use App\Services\CompletionEmailService;
use App\Services\SigningTokenService;
use App\Services\SocketNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SigningSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeDoc(): Document
    {
        $owner = User::factory()->create();

        return Document::factory()->create(['user_id' => $owner->id]);
    }

    public function test_serve_without_token_is_forbidden(): void
    {
        Storage::fake('local');
        $doc = $this->makeDoc();
        Storage::disk('local')->put($doc->file_path, '%PDF-1.4');

        $this->get("/signing/{$doc->uuid}/serve/original")->assertForbidden();
    }

    public function test_serve_with_invalid_token_is_forbidden(): void
    {
        Storage::fake('local');
        $doc = $this->makeDoc();
        Storage::disk('local')->put($doc->file_path, '%PDF-1.4');

        $this->partialMock(SigningTokenService::class, function ($m) {
            $m->shouldReceive('validate')->andReturn(null);
        });

        $this->get("/signing/{$doc->uuid}/serve/original?t=" . str_repeat('a', 64))->assertForbidden();
    }

    public function test_recipient_cannot_create_admin_fields_via_base_param(): void
    {
        Storage::fake('local');
        $doc = $this->makeDoc();
        Field::factory()->create(['document_id' => $doc->id, 'required' => false, 'type' => 'text']);
        $recipient = Recipient::factory()->create(['document_id' => $doc->id]);

        $this->partialMock(SigningTokenService::class, function ($m) use ($doc, $recipient) {
            $m->shouldReceive('validate')->andReturn([
                'email' => $recipient->email,
                'document_id' => $doc->id,
                'document_uuid' => $doc->uuid,
                'recipient_id' => $recipient->id,
            ]);
            $m->shouldReceive('refreshTtl', 'revoke');
        });
        $this->mock(SocketNotificationService::class)->shouldIgnoreMissing();
        $this->mock(CompletionEmailService::class)->shouldIgnoreMissing();

        $this->postJson("/signing/{$doc->uuid}/generate?t={$recipient->token}&base=recipient", [
            'values' => [],
            'admin_fields' => [
                ['page' => 1, 'type' => 'text', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 30, 'value' => 'injected'],
            ],
        ])->assertOk();

        $this->assertSame(0, AdminField::count());
    }
}
