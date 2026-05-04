<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
  private const DOCTOR_TRANSITIONS = [
    Appointment::STATUS_CONFIRMED => [
      Appointment::STATUS_CHECKED_IN,
      Appointment::STATUS_NO_SHOW,
    ],
    Appointment::STATUS_CHECKED_IN => [
      Appointment::STATUS_COMPLETED,
    ],
  ];

  public function book(PatientProfile $patient, int $slotId, int $serviceId, string $notes = ''): Appointment
  {
    return DB::transaction(function () use ($patient, $slotId, $serviceId, $notes): Appointment {
      $slot = AvailabilitySlot::lockForUpdate()->findOrFail($slotId);
      $service = MedicalService::findOrFail($serviceId);

      $this->validateSlotForService($slot, $service);

      $appointment = Appointment::create([
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'slot_id' => $slot->id,
        'start_at' => $slot->start_at,
        'end_at' => $slot->end_at,
        'status' => Appointment::STATUS_CONFIRMED,
        'notes' => $notes,
      ]);

      $slot->forceFill(['is_booked' => true])->save();

      return $appointment->load(['patient.user', 'service', 'slot']);
    });
  }

  public function cancelByPatient(Appointment $appointment, string $reason = ''): Appointment
  {
    return DB::transaction(function () use ($appointment, $reason): Appointment {
      $locked = Appointment::with('slot')->lockForUpdate()->findOrFail($appointment->id);
      $this->validateFutureConfirmed($locked, 'cancelled');

      $locked->forceFill([
        'status' => Appointment::STATUS_CANCELLED,
        'cancellation_reason' => $reason,
      ])->save();

      $locked->slot()->lockForUpdate()->firstOrFail()
        ->forceFill(['is_booked' => false])
        ->save();

      return $locked->fresh(['patient.user', 'service', 'slot']);
    });
  }

  public function reschedule(Appointment $appointment, int $newSlotId): Appointment
  {
    return DB::transaction(function () use ($appointment, $newSlotId): Appointment {
      $locked = Appointment::with('service')->lockForUpdate()->findOrFail($appointment->id);
      $this->validateFutureConfirmed($locked, 'rescheduled');

      if ($locked->slot_id === $newSlotId) {
        throw ValidationException::withMessages([
          'slot_id' => 'Seleziona un orario diverso.',
        ]);
      }

      $oldSlot = AvailabilitySlot::lockForUpdate()->findOrFail($locked->slot_id);
      $newSlot = AvailabilitySlot::lockForUpdate()->findOrFail($newSlotId);
      $this->validateSlotForService($newSlot, $locked->service, $locked);

      $oldSlot->forceFill(['is_booked' => false])->save();
      $newSlot->forceFill(['is_booked' => true])->save();

      $locked->forceFill([
        'slot_id' => $newSlot->id,
        'start_at' => $newSlot->start_at,
        'end_at' => $newSlot->end_at,
        'status' => Appointment::STATUS_CONFIRMED,
        'cancellation_reason' => null,
      ])->save();

      return $locked->fresh(['patient.user', 'service', 'slot']);
    });
  }

  public function updateByDoctor(Appointment $appointment, string $nextStatus): Appointment
  {
    $this->validateChoice($nextStatus, [
      Appointment::STATUS_CHECKED_IN,
      Appointment::STATUS_COMPLETED,
      Appointment::STATUS_NO_SHOW,
    ]);

    return $this->updateStatus($appointment, $nextStatus, self::DOCTOR_TRANSITIONS);
  }

  public function validateSlotForService(
    AvailabilitySlot $slot,
    MedicalService $service,
    ?Appointment $excludingAppointment = null,
  ): void {
    $errors = [];

    if (! $service->is_active) {
      $errors['service_id'] = 'Questa prestazione non e attiva.';
    }
    if ($slot->is_blocked || $slot->is_booked || $slot->start_at->isPast()) {
      $errors['slot_id'] = 'Questo orario non e piu disponibile.';
    }

    $activeAppointmentQuery = Appointment::query()
      ->where('slot_id', $slot->id)
      ->whereIn('status', Appointment::ACTIVE_SLOT_STATUSES);
    if ($excludingAppointment) {
      $activeAppointmentQuery->whereKeyNot($excludingAppointment->id);
    }
    if ($activeAppointmentQuery->exists()) {
      $errors['slot_id'] = 'Questo orario non e piu disponibile.';
    }

    if ($errors !== []) {
      throw ValidationException::withMessages($errors);
    }
  }

  private function updateStatus(Appointment $appointment, string $nextStatus, array $transitions): Appointment
  {
    return DB::transaction(function () use ($appointment, $nextStatus, $transitions): Appointment {
      $locked = Appointment::with('slot')->lockForUpdate()->findOrFail($appointment->id);
      $this->validateTransition($locked, $nextStatus, $transitions);

      if ($locked->status === $nextStatus) {
        return $locked->fresh(['patient.user', 'service', 'slot']);
      }

      if ($nextStatus === Appointment::STATUS_CANCELLED && $locked->start_at->isFuture()) {
        $locked->slot()->lockForUpdate()->firstOrFail()
          ->forceFill(['is_booked' => false])
          ->save();
      }

      $locked->forceFill(['status' => $nextStatus])->save();

      return $locked->fresh(['patient.user', 'service', 'slot']);
    });
  }

  private function validateFutureConfirmed(Appointment $appointment, string $action): void
  {
    $labels = [
      'cancelled' => 'annullati',
      'rescheduled' => 'spostati',
    ];
    $label = $labels[$action] ?? $action;

    if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
      throw ValidationException::withMessages([
        'status' => "Solo gli appuntamenti confermati possono essere {$label}.",
      ]);
    }

    if ($appointment->start_at->isPast()) {
      throw ValidationException::withMessages([
        'start_at' => "Gli appuntamenti passati non possono essere {$label}.",
      ]);
    }
  }

  private function validateChoice(string $nextStatus, array $allowed): void
  {
    if (! in_array($nextStatus, $allowed, true)) {
      throw ValidationException::withMessages([
        'status' => 'Seleziona uno stato valido.',
      ]);
    }
  }

  private function validateTransition(Appointment $appointment, string $nextStatus, array $transitions): void
  {
    if ($appointment->status === $nextStatus) {
      return;
    }

    if (! in_array($nextStatus, $transitions[$appointment->status] ?? [], true)) {
      throw ValidationException::withMessages([
        'status' => 'Questa transizione di stato non e consentita.',
      ]);
    }
  }
}
