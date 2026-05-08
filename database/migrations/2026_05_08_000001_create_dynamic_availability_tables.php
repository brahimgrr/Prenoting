<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('working_hours', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('doctor_profile_id')->constrained('doctor_profiles')->cascadeOnDelete();
      $table->unsignedTinyInteger('weekday')->index();
      $table->time('start_time');
      $table->time('end_time');
      $table->date('effective_from')->nullable()->index();
      $table->date('effective_until')->nullable()->index();
      $table->boolean('is_active')->default(true)->index();
      $table->timestamps();
      $table->index(['doctor_profile_id', 'weekday', 'is_active']);
    });

    Schema::create('special_openings', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('doctor_profile_id')->constrained('doctor_profiles')->cascadeOnDelete();
      $table->date('date')->index();
      $table->time('start_time');
      $table->time('end_time');
      $table->string('note')->nullable();
      $table->timestamps();
      $table->index(['doctor_profile_id', 'date']);
    });

    Schema::create('closures', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('doctor_profile_id')->constrained('doctor_profiles')->cascadeOnDelete();
      $table->date('date')->index();
      $table->time('start_time')->nullable();
      $table->time('end_time')->nullable();
      $table->string('reason')->nullable();
      $table->timestamps();
      $table->index(['doctor_profile_id', 'date']);
    });

    Schema::table('appointments', function (Blueprint $table): void {
      $table->foreignId('doctor_profile_id')
        ->nullable()
        ->after('patient_id')
        ->constrained('doctor_profiles')
        ->restrictOnDelete();
    });

    $doctorId = DB::table('doctor_profiles')->orderBy('id')->value('id');
    if ($doctorId) {
      DB::table('appointments')
        ->whereNull('doctor_profile_id')
        ->update(['doctor_profile_id' => $doctorId]);

      $this->convertFutureSlots($doctorId);
    }

    Schema::table('appointments', function (Blueprint $table): void {
      if (Schema::hasColumn('appointments', 'slot_id')) {
        $table->dropForeign(['slot_id']);
        $table->dropColumn('slot_id');
      }
    });

    Schema::dropIfExists('availability_slots');
  }

  public function down(): void
  {
    Schema::create('availability_slots', function (Blueprint $table): void {
      $table->id();
      $table->dateTime('start_at')->index();
      $table->dateTime('end_at');
      $table->boolean('is_blocked')->default(false)->index();
      $table->boolean('is_booked')->default(false)->index();
      $table->timestamps();
      $table->unique('start_at');
    });

    Schema::table('appointments', function (Blueprint $table): void {
      if (! Schema::hasColumn('appointments', 'slot_id')) {
        $table->foreignId('slot_id')
          ->nullable()
          ->after('service_id')
          ->constrained('availability_slots')
          ->restrictOnDelete();
      }
      if (Schema::hasColumn('appointments', 'doctor_profile_id')) {
        $table->dropForeign(['doctor_profile_id']);
        $table->dropColumn('doctor_profile_id');
      }
    });

    Schema::dropIfExists('closures');
    Schema::dropIfExists('special_openings');
    Schema::dropIfExists('working_hours');
  }

  private function convertFutureSlots(int $doctorId): void
  {
    if (! Schema::hasTable('availability_slots')) {
      return;
    }

    $futureSlots = DB::table('availability_slots')
      ->where('start_at', '>=', CarbonImmutable::now()->toDateTimeString())
      ->orderBy('start_at')
      ->get();

    $futureSlots
      ->where('is_blocked', true)
      ->each(function ($slot) use ($doctorId): void {
        $start = CarbonImmutable::parse($slot->start_at);
        $end = CarbonImmutable::parse($slot->end_at);
        DB::table('closures')->insert([
          'doctor_profile_id' => $doctorId,
          'date' => $start->toDateString(),
          'start_time' => $start->format('H:i:s'),
          'end_time' => $end->format('H:i:s'),
          'reason' => 'Disponibilita bloccata',
          'created_at' => now(),
          'updated_at' => now(),
        ]);
      });

    $futureSlots
      ->where('is_blocked', false)
      ->groupBy(fn ($slot) => CarbonImmutable::parse($slot->start_at)->toDateString())
      ->each(function (Collection $slots) use ($doctorId): void {
        $windowStart = null;
        $previousEnd = null;

        foreach ($slots as $slot) {
          $start = CarbonImmutable::parse($slot->start_at);
          $end = CarbonImmutable::parse($slot->end_at);

          if ($windowStart === null) {
            $windowStart = $start;
            $previousEnd = $end;
            continue;
          }

          if (! $start->equalTo($previousEnd)) {
            $this->insertSpecialOpening($doctorId, $windowStart, $previousEnd);
            $windowStart = $start;
          }

          $previousEnd = $end;
        }

        if ($windowStart && $previousEnd) {
          $this->insertSpecialOpening($doctorId, $windowStart, $previousEnd);
        }
      });
  }

  private function insertSpecialOpening(int $doctorId, CarbonImmutable $start, CarbonImmutable $end): void
  {
    DB::table('special_openings')->insert([
      'doctor_profile_id' => $doctorId,
      'date' => $start->toDateString(),
      'start_time' => $start->format('H:i:s'),
      'end_time' => $end->format('H:i:s'),
      'note' => 'Disponibilita migrata',
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }
};
