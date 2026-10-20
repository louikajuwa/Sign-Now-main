<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('field_uid', 100);
            $table->unsignedInteger('page')->default(1);
            $table->enum('type', ['text', 'date', 'sign', 'initials'])->default('text');
            $table->float('pos_x')->default(0);
            $table->float('pos_y')->default(0);
            $table->float('width')->default(100);
            $table->float('height')->default(24);
            $table->string('label')->default('');
            $table->string('name', 120)->default('');
            $table->boolean('required')->default(false);
            $table->float('font_size')->nullable();
            $table->string('assigned_to')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'name']);
            $table->index('field_uid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
