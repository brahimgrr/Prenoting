<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\MedicalService;
use Illuminate\Http\Request;

class PatientBookingWizardViewData
{
    public function __construct(
        private readonly AvailabilityService           $availability,
        private readonly PatientBookingCalendarService $calendar,
    )
    {
    }

    public function booking(Request $request): array
    {
        $selectedService = $request->integer('service_id')
            ? MedicalService::with('doctor.user')->where('is_active', true)->find($request->integer('service_id'))
            : null;
        $doctor = $selectedService?->doctor ?? $this->availability->primaryDoctor();

        return $this->calendar->build($doctor, $selectedService, $request->query()) + [
                'services' => $this->activeServices(),
                'weekPartialUrl' => url('/patient/book/week'),
                'baseUrl' => url('/patient/book'),
                'formAction' => '/appointments',
                'isReschedule' => false,
            ];
    }

    private function activeServices()
    {
        return MedicalService::where('is_active', true)
            ->whereHas('doctor.user', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    public function reschedule(Request $request, Appointment $appointment): array
    {
        $appointment->loadMissing(['service.doctor.user']);
        $doctor = $appointment->doctor ?? $this->availability->primaryDoctor();
        $service = $appointment->service;

        return $this->calendar->build($doctor, $service, $request->query(), $appointment) + [
                'appointment' => $appointment,
                'services' => $this->activeServices(),
                'weekPartialUrl' => url("/appointments/{$appointment->id}/edit/week"),
                'baseUrl' => url("/appointments/{$appointment->id}/edit"),
                'formAction' => "/appointments/{$appointment->id}/reschedule",
                'isReschedule' => true,
            ];
    }
}
