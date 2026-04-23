import pytest
from rest_framework.test import APIClient

from providers.models import Specialty
from services.models import MedicalService


@pytest.mark.django_db
def test_services_endpoint_returns_active_services():
    cardiology = Specialty.objects.create(name="Cardiology")
    dermatology = Specialty.objects.create(name="Dermatology")
    active_service = MedicalService.objects.create(
        name="Cardiology consultation",
        category=MedicalService.Category.VISIT,
        specialty=cardiology,
        duration_minutes=30,
        price="120.00",
    )
    inactive_service = MedicalService.objects.create(
        name="Dermatology follow-up",
        specialty=dermatology,
        is_active=False,
    )

    response = APIClient().get("/api/services/")

    assert response.status_code == 200
    service_ids = {service["id"] for service in response.data}
    assert service_ids == {active_service.id}
    assert inactive_service.id not in service_ids
    assert response.data[0]["specialty_name"] == "Cardiology"


@pytest.mark.django_db
def test_services_endpoint_filters_by_name_search():
    specialty = Specialty.objects.create(name="Cardiology")
    matched = MedicalService.objects.create(
        name="Cardio check",
        specialty=specialty,
    )
    MedicalService.objects.create(
        name="Skin screening",
        specialty=specialty,
    )

    response = APIClient().get("/api/services/", {"search": "cardio"})

    assert response.status_code == 200
    assert [service["id"] for service in response.data] == [matched.id]


@pytest.mark.django_db
def test_services_endpoint_rejects_malformed_specialty_filter():
    response = APIClient().get("/api/services/", {"specialty": "abc"})

    assert response.status_code == 400
    assert "specialty" in response.data
