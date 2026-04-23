from datetime import timedelta

import pytest
from django.contrib.auth import get_user_model
from django.contrib.auth.models import Group
from django.utils import timezone
from rest_framework.test import APIClient

from accounts.models import PatientProfile
from appointments.models import Appointment, AppointmentStatusHistory
from clinics.models import ClinicLocation
from providers.models import DoctorProfile, Specialty
from scheduling.models import AvailabilitySlot
from services.models import MedicalService


def authenticated_client(user):
    client = APIClient()
    client.force_authenticate(user=user)
    return client


def create_patient(username):
    user = get_user_model().objects.create_user(username=username, password="password")
    patient = PatientProfile.objects.create(user=user, phone="555-0100")
    return user, patient


def create_doctor(username, display_name, specialty):
    user = get_user_model().objects.create_user(username=username, password="password")
    doctor = DoctorProfile.objects.create(
        user=user,
        display_name=display_name,
        specialty=specialty,
    )
    return user, doctor


def create_staff(username="staff"):
    user = get_user_model().objects.create_user(username=username, password="password")
    staff_group, _ = Group.objects.get_or_create(name="Staff")
    user.groups.add(staff_group)
    return user


def create_service(name, specialty):
    return MedicalService.objects.create(name=name, specialty=specialty)


def create_slot(doctor, clinic, offset_hours):
    start_at = timezone.now() + timedelta(hours=offset_hours)
    return AvailabilitySlot.objects.create(
        doctor=doctor,
        clinic=clinic,
        start_at=start_at,
        end_at=start_at + timedelta(minutes=30),
        is_booked=True,
    )


def create_appointment(patient, doctor, service, clinic, offset_hours, status=None):
    slot = create_slot(doctor, clinic, offset_hours)
    return Appointment.objects.create(
        patient=patient,
        doctor=doctor,
        service=service,
        clinic=clinic,
        slot=slot,
        start_at=slot.start_at,
        end_at=slot.end_at,
        status=status or Appointment.Status.CONFIRMED,
    )


def appointment_ids(response):
    return [appointment["id"] for appointment in response.data]


@pytest.fixture
def dashboard_context():
    specialty = Specialty.objects.create(name="Cardiology")
    other_specialty = Specialty.objects.create(name="Dermatology")
    doctor_user, doctor = create_doctor("doctor", "Dr. Avery Heart", specialty)
    other_doctor_user, other_doctor = create_doctor(
        "other-doctor",
        "Dr. Blake Skin",
        other_specialty,
    )
    patient_user, patient = create_patient("patient")
    _, other_patient = create_patient("other-patient")
    service = create_service("Cardiology consultation", specialty)
    other_service = create_service("Dermatology visit", other_specialty)
    clinic = ClinicLocation.objects.create(name="Downtown Clinic", address="1 Main St")
    other_clinic = ClinicLocation.objects.create(name="Uptown Clinic", address="2 Main St")
    return {
        "doctor_user": doctor_user,
        "doctor": doctor,
        "other_doctor_user": other_doctor_user,
        "other_doctor": other_doctor,
        "patient_user": patient_user,
        "patient": patient,
        "other_patient": other_patient,
        "service": service,
        "other_service": other_service,
        "clinic": clinic,
        "other_clinic": other_clinic,
    }


@pytest.mark.django_db
def test_doctor_schedule_returns_only_logged_in_doctor_appointments(dashboard_context):
    own_appointment = create_appointment(
        dashboard_context["patient"],
        dashboard_context["doctor"],
        dashboard_context["service"],
        dashboard_context["clinic"],
        offset_hours=24,
    )
    create_appointment(
        dashboard_context["other_patient"],
        dashboard_context["other_doctor"],
        dashboard_context["other_service"],
        dashboard_context["other_clinic"],
        offset_hours=25,
    )

    response = authenticated_client(dashboard_context["doctor_user"]).get(
        "/api/appointments/doctor/schedule/",
    )

    assert response.status_code == 200
    assert appointment_ids(response) == [own_appointment.id]


@pytest.mark.django_db
@pytest.mark.parametrize(
    "next_status",
    [
        Appointment.Status.CHECKED_IN,
        Appointment.Status.COMPLETED,
        Appointment.Status.NO_SHOW,
    ],
)
def test_doctor_can_update_own_appointment_status(dashboard_context, next_status):
    appointment = create_appointment(
        dashboard_context["patient"],
        dashboard_context["doctor"],
        dashboard_context["service"],
        dashboard_context["clinic"],
        offset_hours=24,
    )

    response = authenticated_client(dashboard_context["doctor_user"]).post(
        f"/api/appointments/doctor/{appointment.id}/status/",
        {"status": next_status},
        format="json",
    )

    assert response.status_code == 200
    appointment.refresh_from_db()
    assert appointment.status == next_status
    history = AppointmentStatusHistory.objects.get(appointment=appointment)
    assert history.previous_status == Appointment.Status.CONFIRMED
    assert history.new_status == next_status
    assert history.changed_by == dashboard_context["doctor_user"]


@pytest.mark.django_db
def test_staff_endpoint_returns_appointments_across_all_doctors(dashboard_context):
    staff_user = create_staff()
    first_appointment = create_appointment(
        dashboard_context["patient"],
        dashboard_context["doctor"],
        dashboard_context["service"],
        dashboard_context["clinic"],
        offset_hours=24,
    )
    second_appointment = create_appointment(
        dashboard_context["other_patient"],
        dashboard_context["other_doctor"],
        dashboard_context["other_service"],
        dashboard_context["other_clinic"],
        offset_hours=25,
    )

    response = authenticated_client(staff_user).get("/api/appointments/staff/")

    assert response.status_code == 200
    assert set(appointment_ids(response)) == {first_appointment.id, second_appointment.id}


@pytest.mark.django_db
def test_staff_can_filter_appointments(dashboard_context):
    staff_user = create_staff()
    target_appointment = create_appointment(
        dashboard_context["patient"],
        dashboard_context["doctor"],
        dashboard_context["service"],
        dashboard_context["clinic"],
        offset_hours=24,
        status=Appointment.Status.CHECKED_IN,
    )
    create_appointment(
        dashboard_context["other_patient"],
        dashboard_context["other_doctor"],
        dashboard_context["other_service"],
        dashboard_context["other_clinic"],
        offset_hours=49,
        status=Appointment.Status.CONFIRMED,
    )
    target_date = target_appointment.start_at.date().isoformat()
    client = authenticated_client(staff_user)

    filter_cases = [
        {"date": target_date},
        {"clinic": dashboard_context["clinic"].id},
        {"doctor": dashboard_context["doctor"].id},
        {"service": dashboard_context["service"].id},
        {"status": Appointment.Status.CHECKED_IN},
    ]

    for filters in filter_cases:
        response = client.get("/api/appointments/staff/", filters)

        assert response.status_code == 200
        assert appointment_ids(response) == [target_appointment.id]


@pytest.mark.django_db
def test_staff_can_update_any_appointment_status(dashboard_context):
    staff_user = create_staff()
    appointment = create_appointment(
        dashboard_context["patient"],
        dashboard_context["doctor"],
        dashboard_context["service"],
        dashboard_context["clinic"],
        offset_hours=24,
    )

    response = authenticated_client(staff_user).post(
        f"/api/appointments/staff/{appointment.id}/status/",
        {"status": Appointment.Status.CANCELLED},
        format="json",
    )

    assert response.status_code == 200
    appointment.refresh_from_db()
    assert appointment.status == Appointment.Status.CANCELLED
    history = AppointmentStatusHistory.objects.get(appointment=appointment)
    assert history.previous_status == Appointment.Status.CONFIRMED
    assert history.new_status == Appointment.Status.CANCELLED
    assert history.changed_by == staff_user


@pytest.mark.django_db
def test_patient_cannot_access_doctor_or_staff_dashboard_endpoints(dashboard_context):
    appointment = create_appointment(
        dashboard_context["patient"],
        dashboard_context["doctor"],
        dashboard_context["service"],
        dashboard_context["clinic"],
        offset_hours=24,
    )
    client = authenticated_client(dashboard_context["patient_user"])

    responses = [
        client.get("/api/appointments/doctor/schedule/"),
        client.post(
            f"/api/appointments/doctor/{appointment.id}/status/",
            {"status": Appointment.Status.CHECKED_IN},
            format="json",
        ),
        client.get("/api/appointments/staff/"),
        client.post(
            f"/api/appointments/staff/{appointment.id}/status/",
            {"status": Appointment.Status.CHECKED_IN},
            format="json",
        ),
    ]

    assert [response.status_code for response in responses] == [403, 403, 403, 403]
