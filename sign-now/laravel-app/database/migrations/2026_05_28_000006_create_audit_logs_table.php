<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 64);
            $table->char('doc_uuid', 36)->nullable()->index();
            $table->unsignedBigInteger('doc_id')->nullable();
            $table->string('performed_by')->nullable();
            $table->enum('performed_by_type', ['admin', 'recipient', 'system'])->default('admin');
            $table->string('ip_address', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
