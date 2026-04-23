import pytest
from django.contrib.auth import get_user_model
from rest_framework.test import APIClient

from providers.models import DoctorProfile, Specialty


def create_doctor(username, display_name, specialty, is_active=True):
    user = get_user_model().objects.create_user(username=username, password="password")
    return DoctorProfile.objects.create(
        user=user,
        display_name=display_name,
        specialty=specialty,
        bio="Available for consultations.",
        license_number=f"LIC-{username}",
        is_active=is_active,
    )


@pytest.mark.django_db
def test_doctors_endpoint_returns_active_doctors():
    cardiology = Specialty.objects.create(name="Cardiology")
    active_doctor = create_doctor("active-doctor", "Dr. Ada Heart", cardiology)
    inactive_doctor = create_doctor(
        "inactive-doctor",
        "Dr. Bert Away",
        cardiology,
        is_active=False,
    )

    response = APIClient().get("/api/doctors/")

    assert response.status_code == 200
    doctor_ids = {doctor["id"] for doctor in response.data}
    assert doctor_ids == {active_doctor.id}
    assert inactive_doctor.id not in doctor_ids
    assert response.data[0]["specialty_name"] == "Cardiology"


@pytest.mark.django_db
def test_doctors_endpoint_filters_by_specialty():
    cardiology = Specialty.objects.create(name="Cardiology")
    dermatology = Specialty.objects.create(name="Dermatology")
    matched = create_doctor("cardio-doctor", "Dr. Cara Heart", cardiology)
    create_doctor("derm-doctor", "Dr. Dana Skin", dermatology)

    response = APIClient().get("/api/doctors/", {"specialty": cardiology.id})

    assert response.status_code == 200
    assert [doctor["id"] for doctor in response.data] == [matched.id]


@pytest.mark.django_db
def test_doctors_endpoint_rejects_malformed_specialty_filter():
    response = APIClient().get("/api/doctors/", {"specialty": "abc"})

    assert response.status_code == 400
    assert "specialty" in response.data
