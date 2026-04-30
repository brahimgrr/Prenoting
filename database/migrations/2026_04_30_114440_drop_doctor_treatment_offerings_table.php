<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('doctor_treatment_offerings')) {
      DB::table('doctor_treatment_offerings')
        ->orderBy('id')
        ->get()
        ->each(function ($offering): void {
          DB::table('medical_services')->updateOrInsert(
            ['name' => $offering->name],
            [
              'category' => $offering->category,
              'duration_minutes' => $offering->duration_minutes,
              'price' => $offering->price,
              'is_active' => $offering->is_active,
              'created_at' => $offering->created_at ?? now(),
              'updated_at' => now(),
            ],
          );
        });
    }

    DB::table('medical_services')
      ->select('name')
      ->groupBy('name')
      ->havingRaw('COUNT(*) > 1')
      ->pluck('name')
      ->each(function (string $name): void {
        $ids = DB::table('medical_services')
          ->where('name', $name)
          ->orderBy('id')
          ->pluck('id');
        $keepId = $ids->shift();

        if ($ids->isEmpty()) {
          return;
        }

        DB::table('appointments')
          ->whereIn('service_id', $ids)
          ->update(['service_id' => $keepId]);
        DB::table('medical_services')
          ->whereIn('id', $ids)
          ->delete();
      });

    Schema::table('medical_services', function (Blueprint $table): void {
      $table->unique('name');
    });

    Schema::dropIfExists('doctor_treatment_offerings');
  }

  public function down(): void
  {
    Schema::table('medical_services', function (Blueprint $table): void {
      $table->dropUnique(['name']);
    });

    Schema::create('doctor_treatment_offerings', function (Blueprint $table): void {
      $table->id();
      $table->string('name', 160)->unique();
      $table->string('category', 16)->index();
      $table->unsignedInteger('duration_minutes')->default(30);
      $table->decimal('price', 8, 2)->nullable();
      $table->boolean('is_active')->default(true)->index();
      $table->timestamps();
    });
  }
};
