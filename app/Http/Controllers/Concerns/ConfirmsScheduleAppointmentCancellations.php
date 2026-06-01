<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\ScheduleAppointmentConflictsException;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

trait ConfirmsScheduleAppointmentCancellations
{
    protected function redirectWithScheduleConfirmation(
        string                                $redirectTo,
        array                                 $confirmation,
        ScheduleAppointmentConflictsException $exception,
    ): RedirectResponse
    {
        return redirect($redirectTo)
            ->withInput()
            ->with('schedule_confirmation', array_merge($confirmation, [
                'reason' => $exception->reason(),
                'appointments' => $exception->appointments()
                    ->unique('id')
                    ->values()
                    ->map(fn(Appointment $appointment): array => [
                        'id' => $appointment->id,
                        'patient_name' => $appointment->patientName(),
                        'service_name' => $appointment->service?->name ?? 'Appuntamento',
                        'starts_at' => CarbonImmutable::parse($appointment->start_at)->format('d/m/Y H:i'),
                        'ends_at' => CarbonImmutable::parse($appointment->end_at)->format('H:i'),
                    ])
                    ->all(),
            ]));
    }
}
