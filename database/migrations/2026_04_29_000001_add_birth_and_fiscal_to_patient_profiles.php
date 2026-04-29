<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_profiles', function (Blueprint $table): void {
            $table->string('place_of_birth', 160)->default('')->after('date_of_birth');
            $table->string('codice_fiscale', 16)->nullable()->after('identity_code');
        });
    }

    public function down(): void
    {
        Schema::table('patient_profiles', function (Blueprint $table): void {
            $table->dropColumn(['place_of_birth', 'codice_fiscale']);
        });
    }
};
