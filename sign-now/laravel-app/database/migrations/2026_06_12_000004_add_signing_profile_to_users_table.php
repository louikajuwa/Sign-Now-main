<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lastname')->default('')->after('name');
            $table->string('company')->default('')->after('email');
            $table->string('phone', 64)->default('')->after('company');
            $table->string('street')->default('')->after('phone');
            $table->string('apt', 64)->default('')->after('street');
            $table->string('city', 128)->default('')->after('apt');
            $table->string('state', 128)->default('')->after('city');
            $table->string('zip', 32)->default('')->after('state');
            $table->string('country', 128)->default('')->after('zip');
            $table->longText('signature')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'lastname', 'company', 'phone', 'street', 'apt',
                'city', 'state', 'zip', 'country', 'signature',
            ]);
        });
    }
};
