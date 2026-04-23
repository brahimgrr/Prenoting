from datetime import timedelta

import pytest
from django.contrib.auth import get_user_model
from django.utils import timezone

from accounts.models import PatientProfile
from appointments.models import Appointment
from clinics.models import ClinicLocation
from providers.models import DoctorProfile, Specialty
from scheduling.models import AvailabilitySlot
from services.models import DoctorService, MedicalService


@pytest.mark.django_db
def test_appointment_string_and_slot_relation():
    User = get_user_model()
    patient_user = User.objects.create_user(username="patient", password="password")
    doctor_user = User.objects.create_user(username="doctor", password="password")
    patient = PatientProfile.objects.create(user=patient_user, phone="555-0100")
    specialty = Specialty.objects.create(name="Cardiology")
    doctor = DoctorProfile.objects.create(
        user=doctor_user,
        display_name="Dr. Avery Heart",
        specialty=specialty,
    )
    service = MedicalService.objects.create(
        name="Cardiology consultation",
        specialty=specialty,
    )
    DoctorService.objects.create(doctor=doctor, service=service)
    clinic = ClinicLocation.objects.create(name="Downtown Clinic", address="1 Main St")
    start_at = timezone.now() + timedelta(days=1)
    end_at = start_at + timedelta(minutes=30)
    slot = AvailabilitySlot.objects.create(
        doctor=doctor,
        clinic=clinic,
        start_at=start_at,
        end_at=end_at,
    )
    appointment = Appointment.objects.create(
        patient=patient,
        doctor=doctor,
        service=service,
        clinic=clinic,
        slot=slot,
        start_at=start_at,
        end_at=end_at,
    )

    assert "Cardiology consultation" in str(appointment)
    assert appointment.status == Appointment.Status.CONFIRMED
