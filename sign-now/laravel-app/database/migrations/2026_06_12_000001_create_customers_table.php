<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 64)->nullable()->unique();
            $table->string('name')->default('');
            $table->string('lastname')->default('');
            $table->string('email')->unique();
            $table->string('company')->default('');
            $table->string('phone', 64)->default('');
            $table->string('street')->default('');
            $table->string('apt', 64)->default('');
            $table->string('city', 128)->default('');
            $table->string('state', 128)->default('');
            $table->string('zip', 32)->default('');
            $table->string('country', 128)->default('');
            $table->timestamps();

            $table->index(['name', 'lastname']);
            $table->index('company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
