<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->after('id');
            $table->index('clinic_id');
            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();

            $table->index(['clinic_id', 'dentist_user_id', 'start_at']);
            $table->index(['clinic_id', 'patient_user_id', 'start_at']);
            $table->index(['clinic_id', 'status', 'start_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropIndex(['clinic_id']);
            $table->dropIndex(['clinic_id', 'dentist_user_id', 'start_at']);
            $table->dropIndex(['clinic_id', 'patient_user_id', 'start_at']);
            $table->dropIndex(['clinic_id', 'status', 'start_at']);
            $table->dropColumn('clinic_id');
        });
    }
};
