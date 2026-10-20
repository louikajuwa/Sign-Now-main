<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreignId('recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('output_path', 512)->default('');
            $table->string('ip_address', 45)->default('');
            $table->string('user_agent')->default('');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('recipients', function (Blueprint $table) {
            $table->foreign('submission_id')->references('id')->on('submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });
        Schema::dropIfExists('submissions');
    }
};
