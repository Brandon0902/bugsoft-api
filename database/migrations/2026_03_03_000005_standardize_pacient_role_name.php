<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'client')->update(['role' => 'pacient']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'pacient')->update(['role' => 'client']);
    }
};
