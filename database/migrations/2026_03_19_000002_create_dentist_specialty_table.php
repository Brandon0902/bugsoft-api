<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dentist_specialty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dentist_profile_id')->constrained('dentist_profiles')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['dentist_profile_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dentist_specialty');
    }
};
