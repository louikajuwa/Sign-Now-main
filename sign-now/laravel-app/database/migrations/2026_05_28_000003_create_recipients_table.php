<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->default('');
            $table->char('token', 64)->unique();
            $table->enum('status', ['pending', 'sent', 'opened', 'signed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'email']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
