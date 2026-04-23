import pytest
from rest_framework.test import APIClient


@pytest.mark.django_db
def test_patient_can_register_and_fetch_current_user():
    client = APIClient()
    response = client.post(
        "/api/auth/register/",
        {
            "username": "sara@example.com",
            "password": "strong-pass-123",
            "first_name": "Sara",
            "last_name": "Conti",
            "phone": "+390000000",
        },
        format="json",
    )
    assert response.status_code == 201
    assert response.data["user"]["role"] == "patient"
    client.login(username="sara@example.com", password="strong-pass-123")
    me = client.get("/api/auth/me/")
    assert me.status_code == 200
    assert me.data["username"] == "sara@example.com"


@pytest.mark.django_db
def test_me_requires_authentication():
    response = APIClient().get("/api/auth/me/")
    assert response.status_code == 403
