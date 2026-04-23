from datetime import timedelta

import pytest
from django.contrib.auth import get_user_model
from django.utils import timezone
from rest_framework.test import APIClient

from clinics.models import ClinicLocation
from providers.models import DoctorProfile, Specialty
from scheduling.models import AvailabilitySlot
from services.models import DoctorService, MedicalService


def create_doctor(username, display_name, specialty):
    user = get_user_model().objects.create_user(username=username, password="password")
    return DoctorProfile.objects.create(
        user=user,
        display_name=display_name,
        specialty=specialty,
    )


@pytest.mark.django_db
def test_availability_endpoint_filters_available_slots_by_service():
    cardiology = Specialty.objects.create(name="Cardiology")
    dermatology = Specialty.objects.create(name="Dermatology")
    cardio_service = MedicalService.objects.create(
        name="Cardiology consultation",
        specialty=cardiology,
    )
    derm_service = MedicalService.objects.create(
        name="Dermatology consultation",
        specialty=dermatology,
    )
    cardio_doctor = create_doctor("cardio-doctor", "Dr. Cara Heart", cardiology)
    derm_doctor = create_doctor("derm-doctor", "Dr. Dana Skin", dermatology)
    DoctorService.objects.create(doctor=cardio_doctor, service=cardio_service)
    DoctorService.objects.create(doctor=derm_doctor, service=derm_service)
    clinic = ClinicLocation.objects.create(name="Downtown Clinic", address="1 Main St")
    future_start = timezone.now() + timedelta(days=1)
    available_slot = AvailabilitySlot.objects.create(
        doctor=cardio_doctor,
        clinic=clinic,
        start_at=future_start,
        end_at=future_start + timedelta(minutes=30),
    )
    blocked_start = future_start + timedelta(hours=1)
    AvailabilitySlot.objects.create(
        doctor=cardio_doctor,
        clinic=clinic,
        start_at=blocked_start,
        end_at=blocked_start + timedelta(minutes=30),
        is_blocked=True,
    )
    booked_start = future_start + timedelta(hours=2)
    AvailabilitySlot.objects.create(
        doctor=cardio_doctor,
        clinic=clinic,
        start_at=booked_start,
        end_at=booked_start + timedelta(minutes=30),
        is_booked=True,
    )
    other_doctor_start = future_start + timedelta(hours=3)
    AvailabilitySlot.objects.create(
        doctor=derm_doctor,
        clinic=clinic,
        start_at=other_doctor_start,
        end_at=other_doctor_start + timedelta(minutes=30),
    )

    response = APIClient().get("/api/availability/", {"service": cardio_service.id})

    assert response.status_code == 200
    assert [slot["id"] for slot in response.data] == [available_slot.id]
    assert response.data[0]["doctor_name"] == "Dr. Cara Heart"
    assert response.data[0]["clinic_name"] == "Downtown Clinic"


@pytest.mark.django_db
def test_availability_endpoint_filters_by_doctor():
    specialty = Specialty.objects.create(name="Cardiology")
    first_doctor = create_doctor("first-doctor", "Dr. First", specialty)
    second_doctor = create_doctor("second-doctor", "Dr. Second", specialty)
    clinic = ClinicLocation.objects.create(name="Downtown Clinic", address="1 Main St")
    start_at = timezone.now() + timedelta(days=1)
    matched_slot = AvailabilitySlot.objects.create(
        doctor=first_doctor,
        clinic=clinic,
        start_at=start_at,
        end_at=start_at + timedelta(minutes=30),
    )
    other_start = start_at + timedelta(hours=1)
    AvailabilitySlot.objects.create(
        doctor=second_doctor,
        clinic=clinic,
        start_at=other_start,
        end_at=other_start + timedelta(minutes=30),
    )

    response = APIClient().get("/api/availability/", {"doctor": first_doctor.id})

    assert response.status_code == 200
    assert [slot["id"] for slot in response.data] == [matched_slot.id]
