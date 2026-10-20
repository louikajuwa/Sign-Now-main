<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('title')->nullable();
            $table->string('file_path', 512);
            $table->unsignedInteger('page_count')->default(1);
            $table->decimal('render_scale', 5, 2)->default(1.50);
            $table->enum('status', ['draft', 'ready', 'filled', 'completed'])->default('draft');
            $table->boolean('is_template')->default(false);
            $table->foreignId('template_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->boolean('is_api_template')->default(false);
            $table->string('api_key', 64)->nullable()->unique();
            $table->boolean('main_nda_api')->default(false);
            $table->timestamps();
            $table->timestamp('last_activity_at')->nullable();

            $table->index('status');
            $table->index('is_template');
            $table->index('is_api_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
