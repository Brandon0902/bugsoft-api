<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'client')->update(['role' => 'pacient']);

        DB::statement("ALTER TABLE users MODIFY role VARCHAR(20) NOT NULL DEFAULT 'pacient'");
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'pacient')->update(['role' => 'client']);

        DB::statement("ALTER TABLE users MODIFY role VARCHAR(20) NOT NULL DEFAULT 'client'");
    }
};
