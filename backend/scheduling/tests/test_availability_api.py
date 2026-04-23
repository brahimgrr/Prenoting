from datetime import timedelta

import pytest
from django.contrib.auth import get_user_model
from django.utils import timezone
from rest_framework.test import APIClient

from clinics.models import ClinicLocation
from providers.models import DoctorProfile, Specialty
from scheduling.models import AvailabilitySlot
from services.models import DoctorService, MedicalService


def create_doctor(username, display_name, specialty, is_active=True):
    user = get_user_model().objects.create_user(username=username, password="password")
    return DoctorProfile.objects.create(
        user=user,
        display_name=display_name,
        specialty=specialty,
        is_active=is_active,
    )


def authenticated_client(user):
    client = APIClient()
    client.force_authenticate(user=user)
    return client


def create_patient(username="patient"):
    return get_user_model().objects.create_user(username=username, password="password")


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


@pytest.mark.django_db
@pytest.mark.parametrize("field", ["doctor", "clinic", "service", "specialty"])
def test_availability_endpoint_rejects_malformed_id_filters(field):
    response = APIClient().get("/api/availability/", {field: "abc"})

    assert response.status_code == 400
    assert field in response.data


@pytest.mark.django_db
def test_availability_endpoint_rejects_invalid_date_filter():
    response = APIClient().get("/api/availability/", {"date": "bad"})

    assert response.status_code == 400
    assert "date" in response.data


@pytest.mark.django_db
def test_availability_endpoint_excludes_inactive_public_entities():
    specialty = Specialty.objects.create(name="Cardiology")
    active_service = MedicalService.objects.create(
        name="Cardiology consultation",
        specialty=specialty,
    )
    inactive_service = MedicalService.objects.create(
        name="Inactive cardiology consultation",
        specialty=specialty,
        is_active=False,
    )
    active_doctor = create_doctor("active-doctor", "Dr. Active", specialty)
    inactive_doctor = create_doctor(
        "inactive-doctor",
        "Dr. Inactive",
        specialty,
        is_active=False,
    )
    DoctorService.objects.create(doctor=active_doctor, service=active_service)
    DoctorService.objects.create(doctor=active_doctor, service=inactive_service)
    DoctorService.objects.create(doctor=inactive_doctor, service=active_service)
    active_clinic = ClinicLocation.objects.create(name="Active Clinic", address="1 Main St")
    inactive_clinic = ClinicLocation.objects.create(
        name="Inactive Clinic",
        address="2 Main St",
        is_active=False,
    )
    start_at = timezone.now() + timedelta(days=1)
    visible_slot = AvailabilitySlot.objects.create(
        doctor=active_doctor,
        clinic=active_clinic,
        start_at=start_at,
        end_at=start_at + timedelta(minutes=30),
    )
    inactive_doctor_start = start_at + timedelta(hours=1)
    AvailabilitySlot.objects.create(
        doctor=inactive_doctor,
        clinic=active_clinic,
        start_at=inactive_doctor_start,
        end_at=inactive_doctor_start + timedelta(minutes=30),
    )
    inactive_clinic_start = start_at + timedelta(hours=2)
    AvailabilitySlot.objects.create(
        doctor=active_doctor,
        clinic=inactive_clinic,
        start_at=inactive_clinic_start,
        end_at=inactive_clinic_start + timedelta(minutes=30),
    )

    active_response = APIClient().get(
        "/api/availability/",
        {"service": active_service.id},
    )
    inactive_response = APIClient().get(
        "/api/availability/",
        {"service": inactive_service.id},
    )

    assert active_response.status_code == 200
    assert [slot["id"] for slot in active_response.data] == [visible_slot.id]
    assert inactive_response.status_code == 200
    assert inactive_response.data == []


@pytest.mark.django_db
def test_doctor_can_create_own_availability_slot():
    specialty = Specialty.objects.create(name="Cardiologia")
    doctor = create_doctor("doctor", "Dott. Verde", specialty)
    clinic = ClinicLocation.objects.create(name="Ambulatorio Centro", address="Via Roma 1")
    start_at = timezone.now() + timedelta(days=2)
    end_at = start_at + timedelta(minutes=30)

    response = authenticated_client(doctor.user).post(
        "/api/availability/doctor/",
        {
            "clinic": clinic.id,
            "start_at": start_at.isoformat(),
            "end_at": end_at.isoformat(),
        },
        format="json",
    )

    assert response.status_code == 201
    slot = AvailabilitySlot.objects.get(id=response.data["id"])
    assert slot.doctor == doctor
    assert slot.clinic == clinic
    assert slot.is_booked is False
    assert slot.is_blocked is False


@pytest.mark.django_db
def test_patient_cannot_create_doctor_availability_slot():
    clinic = ClinicLocation.objects.create(name="Ambulatorio Centro", address="Via Roma 1")
    start_at = timezone.now() + timedelta(days=2)

    response = authenticated_client(create_patient()).post(
        "/api/availability/doctor/",
        {
            "clinic": clinic.id,
            "start_at": start_at.isoformat(),
            "end_at": (start_at + timedelta(minutes=30)).isoformat(),
        },
        format="json",
    )

    assert response.status_code == 403
    assert AvailabilitySlot.objects.count() == 0


@pytest.mark.django_db
def test_doctor_availability_rejects_past_start():
    specialty = Specialty.objects.create(name="Cardiologia")
    doctor = create_doctor("doctor", "Dott. Verde", specialty)
    clinic = ClinicLocation.objects.create(name="Ambulatorio Centro", address="Via Roma 1")
    start_at = timezone.now() - timedelta(minutes=10)

    response = authenticated_client(doctor.user).post(
        "/api/availability/doctor/",
        {
            "clinic": clinic.id,
            "start_at": start_at.isoformat(),
            "end_at": (start_at + timedelta(minutes=30)).isoformat(),
        },
        format="json",
    )

    assert response.status_code == 400
    assert "start_at" in response.data


@pytest.mark.django_db
def test_doctor_availability_rejects_end_before_start():
    specialty = Specialty.objects.create(name="Cardiologia")
    doctor = create_doctor("doctor", "Dott. Verde", specialty)
    clinic = ClinicLocation.objects.create(name="Ambulatorio Centro", address="Via Roma 1")
    start_at = timezone.now() + timedelta(days=2)

    response = authenticated_client(doctor.user).post(
        "/api/availability/doctor/",
        {
            "clinic": clinic.id,
            "start_at": start_at.isoformat(),
            "end_at": (start_at - timedelta(minutes=30)).isoformat(),
        },
        format="json",
    )

    assert response.status_code == 400
    assert "end_at" in response.data


@pytest.mark.django_db
def test_doctor_availability_rejects_overlapping_slot():
    specialty = Specialty.objects.create(name="Cardiologia")
    doctor = create_doctor("doctor", "Dott. Verde", specialty)
    clinic = ClinicLocation.objects.create(name="Ambulatorio Centro", address="Via Roma 1")
    start_at = timezone.now() + timedelta(days=2)
    AvailabilitySlot.objects.create(
        doctor=doctor,
        clinic=clinic,
        start_at=start_at,
        end_at=start_at + timedelta(minutes=60),
    )

    response = authenticated_client(doctor.user).post(
        "/api/availability/doctor/",
        {
            "clinic": clinic.id,
            "start_at": (start_at + timedelta(minutes=15)).isoformat(),
            "end_at": (start_at + timedelta(minutes=45)).isoformat(),
        },
        format="json",
    )

    assert response.status_code == 400
    assert "start_at" in response.data
