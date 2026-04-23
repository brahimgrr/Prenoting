from datetime import timedelta

import pytest
from django.contrib.auth import get_user_model
from django.utils import timezone
from rest_framework.test import APIClient

from accounts.models import PatientProfile
from appointments.models import Appointment, AppointmentStatusHistory
from clinics.models import ClinicLocation
from providers.models import DoctorProfile, Specialty
from scheduling.models import AvailabilitySlot
from services.models import DoctorService, MedicalService


def create_patient(username):
    user = get_user_model().objects.create_user(username=username, password="password")
    patient = PatientProfile.objects.create(user=user, phone="555-0100")
    return user, patient


def create_doctor(username, display_name, specialty, is_active=True):
    user = get_user_model().objects.create_user(username=username, password="password")
    return DoctorProfile.objects.create(
        user=user,
        display_name=display_name,
        specialty=specialty,
        is_active=is_active,
    )


def create_service(name, specialty, is_active=True):
    return MedicalService.objects.create(
        name=name,
        specialty=specialty,
        is_active=is_active,
    )


def create_slot(doctor, clinic, offset_hours=24, is_blocked=False, is_booked=False):
    start_at = timezone.now() + timedelta(hours=offset_hours)
    return AvailabilitySlot.objects.create(
        doctor=doctor,
        clinic=clinic,
        start_at=start_at,
        end_at=start_at + timedelta(minutes=30),
        is_blocked=is_blocked,
        is_booked=is_booked,
    )


def create_booking_context():
    specialty = Specialty.objects.create(name="Cardiology")
    doctor = create_doctor("doctor", "Dr. Avery Heart", specialty)
    service = create_service("Cardiology consultation", specialty)
    DoctorService.objects.create(doctor=doctor, service=service)
    clinic = ClinicLocation.objects.create(name="Downtown Clinic", address="1 Main St")
    slot = create_slot(doctor, clinic)
    return specialty, doctor, service, clinic, slot


def create_appointment(patient, doctor, service, clinic, slot, status=Appointment.Status.CONFIRMED):
    slot.is_booked = True
    slot.save(update_fields=["is_booked"])
    return Appointment.objects.create(
        patient=patient,
        doctor=doctor,
        service=service,
        clinic=clinic,
        slot=slot,
        start_at=slot.start_at,
        end_at=slot.end_at,
        status=status,
    )


def authenticated_client(user):
    client = APIClient()
    client.force_authenticate(user=user)
    return client


@pytest.mark.django_db
def test_appointment_string_and_slot_relation():
    patient_user, patient = create_patient("patient")
    specialty = Specialty.objects.create(name="Cardiology")
    doctor = create_doctor("doctor", "Dr. Avery Heart", specialty)
    service = create_service("Cardiology consultation", specialty)
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


@pytest.mark.django_db
def test_patient_can_book_available_slot_and_slot_is_marked_booked():
    patient_user, patient = create_patient("patient")
    _, doctor, service, clinic, slot = create_booking_context()

    response = authenticated_client(patient_user).post(
        "/api/appointments/",
        {"slot": slot.id, "service": service.id, "notes": "First visit"},
        format="json",
    )

    assert response.status_code == 201
    assert response.data["patient"] == patient.id
    assert response.data["doctor"] == doctor.id
    assert response.data["doctor_name"] == "Dr. Avery Heart"
    assert response.data["service"] == service.id
    assert response.data["service_name"] == "Cardiology consultation"
    assert response.data["clinic"] == clinic.id
    assert response.data["clinic_name"] == "Downtown Clinic"
    assert response.data["slot"] == slot.id
    assert response.data["status"] == Appointment.Status.CONFIRMED
    assert response.data["notes"] == "First visit"
    slot.refresh_from_db()
    assert slot.is_booked is True


@pytest.mark.django_db
def test_second_booking_attempt_for_same_slot_returns_slot_error():
    first_user, _ = create_patient("first-patient")
    second_user, _ = create_patient("second-patient")
    _, _, service, _, slot = create_booking_context()
    authenticated_client(first_user).post(
        "/api/appointments/",
        {"slot": slot.id, "service": service.id},
        format="json",
    )

    response = authenticated_client(second_user).post(
        "/api/appointments/",
        {"slot": slot.id, "service": service.id},
        format="json",
    )

    assert response.status_code == 400
    assert "slot" in response.data


@pytest.mark.django_db
def test_patient_can_list_only_their_own_appointments():
    patient_user, patient = create_patient("patient")
    other_user, other_patient = create_patient("other-patient")
    _, doctor, service, clinic, slot = create_booking_context()
    other_slot = create_slot(doctor, clinic, offset_hours=25)
    own_appointment = Appointment.objects.create(
        patient=patient,
        doctor=doctor,
        service=service,
        clinic=clinic,
        slot=slot,
        start_at=slot.start_at,
        end_at=slot.end_at,
    )
    Appointment.objects.create(
        patient=other_patient,
        doctor=doctor,
        service=service,
        clinic=clinic,
        slot=other_slot,
        start_at=other_slot.start_at,
        end_at=other_slot.end_at,
    )

    response = authenticated_client(patient_user).get("/api/appointments/")
    other_response = authenticated_client(other_user).get("/api/appointments/")

    assert response.status_code == 200
    assert [appointment["id"] for appointment in response.data] == [own_appointment.id]
    assert other_response.status_code == 200
    assert len(other_response.data) == 1
    assert other_response.data[0]["id"] != own_appointment.id


@pytest.mark.django_db
def test_patient_can_cancel_their_own_appointment_and_history_is_created():
    patient_user, patient = create_patient("patient")
    _, doctor, service, clinic, slot = create_booking_context()
    slot.is_booked = True
    slot.save(update_fields=["is_booked"])
    appointment = Appointment.objects.create(
        patient=patient,
        doctor=doctor,
        service=service,
        clinic=clinic,
        slot=slot,
        start_at=slot.start_at,
        end_at=slot.end_at,
    )

    response = authenticated_client(patient_user).post(
        f"/api/appointments/{appointment.id}/cancel/",
        {"cancellation_reason": "Cannot attend"},
        format="json",
    )

    assert response.status_code == 200
    appointment.refresh_from_db()
    slot.refresh_from_db()
    assert appointment.status == Appointment.Status.CANCELLED
    assert appointment.cancellation_reason == "Cannot attend"
    assert slot.is_booked is False
    history = AppointmentStatusHistory.objects.get(appointment=appointment)
    assert history.previous_status == Appointment.Status.CONFIRMED
    assert history.new_status == Appointment.Status.CANCELLED
    assert history.changed_by == patient_user


@pytest.mark.django_db
@pytest.mark.parametrize(
    "blocked_status",
    [
        Appointment.Status.CANCELLED,
        Appointment.Status.CHECKED_IN,
        Appointment.Status.COMPLETED,
        Appointment.Status.NO_SHOW,
    ],
)
def test_patient_cannot_cancel_non_confirmed_appointment(blocked_status):
    patient_user, patient = create_patient("patient")
    _, doctor, service, clinic, slot = create_booking_context()
    appointment = create_appointment(patient, doctor, service, clinic, slot, blocked_status)

    response = authenticated_client(patient_user).post(
        f"/api/appointments/{appointment.id}/cancel/",
        {"cancellation_reason": "Cannot attend"},
        format="json",
    )

    assert response.status_code == 400
    assert "status" in response.data
    appointment.refresh_from_db()
    slot.refresh_from_db()
    assert appointment.status == blocked_status
    assert appointment.cancellation_reason == ""
    assert slot.is_booked is True
    assert AppointmentStatusHistory.objects.filter(appointment=appointment).count() == 0


@pytest.mark.django_db
def test_patient_cannot_cancel_past_appointment():
    patient_user, patient = create_patient("patient")
    _, doctor, service, clinic, slot = create_booking_context()
    past_start = timezone.now() - timedelta(hours=1)
    slot.start_at = past_start
    slot.end_at = past_start + timedelta(minutes=30)
    slot.save(update_fields=["start_at", "end_at"])
    appointment = create_appointment(patient, doctor, service, clinic, slot)

    response = authenticated_client(patient_user).post(
        f"/api/appointments/{appointment.id}/cancel/",
        {"cancellation_reason": "Cannot attend"},
        format="json",
    )

    assert response.status_code == 400
    assert "start_at" in response.data
    appointment.refresh_from_db()
    slot.refresh_from_db()
    assert appointment.status == Appointment.Status.CONFIRMED
    assert appointment.cancellation_reason == ""
    assert slot.is_booked is True
    assert AppointmentStatusHistory.objects.filter(appointment=appointment).count() == 0


@pytest.mark.django_db
def test_patient_can_reschedule_to_different_available_slot_and_history_is_created():
    patient_user, patient = create_patient("patient")
    _, doctor, service, clinic, old_slot = create_booking_context()
    new_slot = create_slot(doctor, clinic, offset_hours=26)
    old_slot.is_booked = True
    old_slot.save(update_fields=["is_booked"])
    appointment = Appointment.objects.create(
        patient=patient,
        doctor=doctor,
        service=service,
        clinic=clinic,
        slot=old_slot,
        start_at=old_slot.start_at,
        end_at=old_slot.end_at,
    )

    response = authenticated_client(patient_user).post(
        f"/api/appointments/{appointment.id}/reschedule/",
        {"slot": new_slot.id},
        format="json",
    )

    assert response.status_code == 200
    appointment.refresh_from_db()
    old_slot.refresh_from_db()
    new_slot.refresh_from_db()
    assert appointment.slot == new_slot
    assert appointment.start_at == new_slot.start_at
    assert appointment.end_at == new_slot.end_at
    assert appointment.status == Appointment.Status.CONFIRMED
    assert old_slot.is_booked is False
    assert new_slot.is_booked is True
    history = AppointmentStatusHistory.objects.get(appointment=appointment)
    assert history.previous_status == Appointment.Status.CONFIRMED
    assert history.new_status == "rescheduled"
    assert history.changed_by == patient_user


@pytest.mark.django_db
@pytest.mark.parametrize(
    "blocked_status",
    [
        Appointment.Status.CANCELLED,
        Appointment.Status.CHECKED_IN,
        Appointment.Status.COMPLETED,
        Appointment.Status.NO_SHOW,
    ],
)
def test_patient_cannot_reschedule_non_confirmed_appointment(blocked_status):
    patient_user, patient = create_patient("patient")
    _, doctor, service, clinic, old_slot = create_booking_context()
    new_slot = create_slot(doctor, clinic, offset_hours=26)
    appointment = create_appointment(patient, doctor, service, clinic, old_slot, blocked_status)

    response = authenticated_client(patient_user).post(
        f"/api/appointments/{appointment.id}/reschedule/",
        {"slot": new_slot.id},
        format="json",
    )

    assert response.status_code == 400
    assert "status" in response.data
    appointment.refresh_from_db()
    old_slot.refresh_from_db()
    new_slot.refresh_from_db()
    assert appointment.status == blocked_status
    assert appointment.slot == old_slot
    assert old_slot.is_booked is True
    assert new_slot.is_booked is False
    assert AppointmentStatusHistory.objects.filter(appointment=appointment).count() == 0


@pytest.mark.django_db
def test_patient_cannot_reschedule_past_appointment():
    patient_user, patient = create_patient("patient")
    _, doctor, service, clinic, old_slot = create_booking_context()
    new_slot = create_slot(doctor, clinic, offset_hours=26)
    past_start = timezone.now() - timedelta(hours=1)
    old_slot.start_at = past_start
    old_slot.end_at = past_start + timedelta(minutes=30)
    old_slot.save(update_fields=["start_at", "end_at"])
    appointment = create_appointment(patient, doctor, service, clinic, old_slot)

    response = authenticated_client(patient_user).post(
        f"/api/appointments/{appointment.id}/reschedule/",
        {"slot": new_slot.id},
        format="json",
    )

    assert response.status_code == 400
    assert "start_at" in response.data
    appointment.refresh_from_db()
    old_slot.refresh_from_db()
    new_slot.refresh_from_db()
    assert appointment.status == Appointment.Status.CONFIRMED
    assert appointment.slot == old_slot
    assert old_slot.is_booked is True
    assert new_slot.is_booked is False
    assert AppointmentStatusHistory.objects.filter(appointment=appointment).count() == 0
