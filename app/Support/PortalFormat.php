<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\MedicalService;

class PortalFormat
{
    public static function service(MedicalService $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'category' => $service->category,
            'duration_minutes' => $service->duration_minutes,
            'price' => $service->price,
        ];
    }

    public static function slot(VirtualAvailabilitySlot $slot): array
    {
        return [
            'slot_key' => $slot->key,
            'slot_start' => $slot->key,
            'start_at' => $slot->start_at?->toISOString(),
            'end_at' => $slot->end_at?->toISOString(),
        ];
    }

    public static function appointment(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'patient' => $appointment->patient_id,
            'patient_name' => $appointment->patientName(),
            'service' => $appointment->service_id,
            'service_name' => $appointment->service?->name,
            'doctor' => $appointment->doctor?->id,
            'start_at' => $appointment->start_at?->toISOString(),
            'end_at' => $appointment->end_at?->toISOString(),
            'status' => $appointment->status,
            'notes' => $appointment->notes,
            'cancellation_reason' => $appointment->cancellation_reason,
        ];
    }
}
