<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Models\MedicalService;

class PortalFormat
{
  public static function service(MedicalService $service): array
  {
    return [
      'id' => $service->id,
      'name' => $service->name,
      'category' => $service->category,
      'specialty' => $service->specialty_id,
      'specialty_name' => $service->specialty?->name,
      'duration_minutes' => $service->duration_minutes,
      'price' => $service->price,
    ];
  }

  public static function doctor(DoctorProfile $doctor): array
  {
    return [
      'id' => $doctor->id,
      'display_name' => $doctor->display_name,
      'specialty' => $doctor->specialty_id,
      'specialty_name' => $doctor->specialty?->name,
      'bio' => $doctor->bio,
      'license_number' => $doctor->license_number,
      'service_ids' => $doctor->services->pluck('id')->values()->all(),
    ];
  }

  public static function slot(AvailabilitySlot $slot): array
  {
    return [
      'id' => $slot->id,
      'doctor' => $slot->doctor_id,
      'doctor_name' => $slot->doctor?->display_name,
      'clinic' => $slot->clinic_id,
      'clinic_name' => $slot->clinic?->name,
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
      'doctor' => $appointment->doctor_id,
      'doctor_name' => $appointment->doctor?->display_name,
      'service' => $appointment->service_id,
      'service_name' => $appointment->service?->name,
      'clinic' => $appointment->clinic_id,
      'clinic_name' => $appointment->clinic?->name,
      'slot' => $appointment->slot_id,
      'start_at' => $appointment->start_at?->toISOString(),
      'end_at' => $appointment->end_at?->toISOString(),
      'status' => $appointment->status,
      'notes' => $appointment->notes,
      'cancellation_reason' => $appointment->cancellation_reason,
    ];
  }
}
