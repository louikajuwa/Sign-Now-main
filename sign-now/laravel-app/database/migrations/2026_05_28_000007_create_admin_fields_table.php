<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page')->default(1);
            $table->string('type', 32)->default('text');
            $table->float('pos_x')->default(0);
            $table->float('pos_y')->default(0);
            $table->float('width')->default(100);
            $table->float('height')->default(30);
            $table->longText('value');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_fields');
    }
};
