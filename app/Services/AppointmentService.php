<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Models\PatientProfile;
use App\Models\User;
use App\Support\ScheduleTime;
use App\Support\VirtualAvailabilitySlot;
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

    public function __construct(private readonly AvailabilityService $availability)
    {
    }

    public function book(PatientProfile $patient, string $slotStart, int $serviceId, string $notes = ''): Appointment
    {
        return DB::transaction(function () use ($patient, $slotStart, $serviceId, $notes): Appointment {
            $service = MedicalService::with('doctor')->lockForUpdate()->findOrFail($serviceId);
            $doctor = $this->lockServiceDoctor($service);
            $slot = $this->validateSlotForService($doctor, $service, $slotStart);

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'start_at' => $slot->start_at,
                'end_at' => $slot->end_at,
                'status' => Appointment::STATUS_CONFIRMED,
                'notes' => $notes,
            ]);

            return $appointment->load(Appointment::PORTAL_RELATIONS);
        });
    }

    private function lockServiceDoctor(MedicalService $service): DoctorProfile
    {
        return DoctorProfile::query()->lockForUpdate()->findOrFail($service->doctor_profile_id);
    }

    public function validateSlotForService(
        DoctorProfile  $doctor,
        MedicalService $service,
        string         $slotStart,
        ?Appointment   $excludingAppointment = null,
    ): VirtualAvailabilitySlot
    {
        $errors = [];

        if (!$service->is_active) {
            $errors['service_id'] = 'Questa prestazione non e attiva.';
        }

        $slot = $errors === []
            ? $this->availability->availableSlotByKey($doctor, $service, $slotStart, $excludingAppointment)
            : null;

        if (!$slot) {
            $errors['slot_start'] = 'Questo orario non e piu disponibile.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $slot;
    }

    public function cancelByPatient(Appointment $appointment, string $reason = '', ?User $cancelledBy = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $reason, $cancelledBy): Appointment {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $this->validateFutureConfirmed($locked, 'cancelled');

            $locked->forceFill([
                'status' => Appointment::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_by_role' => Appointment::CANCELLED_BY_PATIENT,
                'cancelled_by_user_id' => $cancelledBy?->id,
                'cancelled_at' => now(),
            ])->save();

            return $this->freshPortalAppointment($locked);
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

        if ($appointment->start_at->lte(now()->addDay())) {
            $messages = [
                'cancelled' => 'Gli appuntamenti non possono essere annullati nelle 24 ore precedenti.',
                'rescheduled' => 'Gli appuntamenti non possono essere spostati nelle 24 ore precedenti.',
            ];

            throw ValidationException::withMessages([
                'start_at' => $messages[$action] ?? 'Gli appuntamenti non possono essere modificati nelle 24 ore precedenti.',
            ]);
        }
    }

    private function freshPortalAppointment(Appointment $appointment): Appointment
    {
        return $appointment->fresh(Appointment::PORTAL_RELATIONS);
    }

    public function cancelByDoctor(Appointment $appointment, string $reason = '', ?User $cancelledBy = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $reason, $cancelledBy): Appointment {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $this->validateFutureActive($locked, 'annullati');

            $locked->forceFill([
                'status' => Appointment::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_by_role' => Appointment::CANCELLED_BY_DOCTOR,
                'cancelled_by_user_id' => $cancelledBy?->id,
                'cancelled_at' => now(),
            ])->save();

            return $this->freshPortalAppointment($locked);
        });
    }

    private function validateFutureActive(Appointment $appointment, string $action): void
    {
        if (!in_array($appointment->status, Appointment::ACTIVE_SLOT_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => "Solo gli appuntamenti attivi possono essere {$action}.",
            ]);
        }

        if ($appointment->start_at->isPast()) {
            throw ValidationException::withMessages([
                'start_at' => "Gli appuntamenti passati non possono essere {$action}.",
            ]);
        }
    }

    public function reschedule(Appointment $appointment, string $newSlotStart): Appointment
    {
        return DB::transaction(function () use ($appointment, $newSlotStart): Appointment {
            $locked = Appointment::with('service.doctor')->lockForUpdate()->findOrFail($appointment->id);
            $this->validateFutureConfirmed($locked, 'rescheduled');

            $doctor = $this->lockServiceDoctor($locked->service);
            $parsedStart = ScheduleTime::parseSlotStart($newSlotStart);

            if ($parsedStart && $locked->start_at->format('Y-m-d\TH:i') === $parsedStart->format('Y-m-d\TH:i')) {
                throw ValidationException::withMessages([
                    'slot_start' => 'Seleziona un orario diverso.',
                ]);
            }

            $newSlot = $this->validateSlotForService($doctor, $locked->service, $newSlotStart, $locked);

            $locked->forceFill([
                'start_at' => $newSlot->start_at,
                'end_at' => $newSlot->end_at,
                'status' => Appointment::STATUS_CONFIRMED,
                'cancellation_reason' => null,
                'cancelled_by_role' => null,
                'cancelled_by_user_id' => null,
                'cancelled_at' => null,
            ])->save();

            return $this->freshPortalAppointment($locked);
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

    private function validateChoice(string $nextStatus, array $allowed): void
    {
        if (!in_array($nextStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Seleziona uno stato valido.',
            ]);
        }
    }

    private function updateStatus(Appointment $appointment, string $nextStatus, array $transitions): Appointment
    {
        return DB::transaction(function () use ($appointment, $nextStatus, $transitions): Appointment {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $this->validateTransition($locked, $nextStatus, $transitions);

            if ($locked->status === $nextStatus) {
                return $this->freshPortalAppointment($locked);
            }

            $locked->forceFill(['status' => $nextStatus])->save();

            return $this->freshPortalAppointment($locked);
        });
    }

    private function validateTransition(Appointment $appointment, string $nextStatus, array $transitions): void
    {
        if ($appointment->status === $nextStatus) {
            return;
        }

        if (!in_array($nextStatus, $transitions[$appointment->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'Questa transizione di stato non e consentita.',
            ]);
        }
    }
}
