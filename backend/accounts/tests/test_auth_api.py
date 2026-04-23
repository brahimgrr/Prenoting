import pytest
from django.contrib.auth import get_user_model
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


@pytest.mark.django_db
def test_register_rejects_short_password():
    response = APIClient().post(
        "/api/auth/register/",
        {
            "username": "short@example.com",
            "password": "short",
            "first_name": "Short",
            "last_name": "Password",
            "phone": "+390000001",
        },
        format="json",
    )

    assert response.status_code == 400
    assert "password" in response.data


@pytest.mark.django_db
def test_login_returns_current_user_for_valid_credentials():
    User = get_user_model()
    User.objects.create_user(username="sara@example.com", password="strong-pass-123")

    response = APIClient().post(
        "/api/auth/login/",
        {
            "username": "sara@example.com",
            "password": "strong-pass-123",
        },
        format="json",
    )

    assert response.status_code == 200
    assert response.data["user"]["username"] == "sara@example.com"


@pytest.mark.django_db
def test_superuser_is_not_reported_as_portal_admin_role():
    User = get_user_model()
    User.objects.create_superuser(
        username="admin",
        password="strong-pass-123",
        email="admin@example.com",
    )
    client = APIClient()
    client.login(username="admin", password="strong-pass-123")

    response = client.get("/api/auth/me/")

    assert response.status_code == 200
    assert response.data["role"] == "user"


@pytest.mark.django_db
def test_login_rejects_invalid_credentials():
    User = get_user_model()
    User.objects.create_user(username="sara@example.com", password="strong-pass-123")

    response = APIClient().post(
        "/api/auth/login/",
        {
            "username": "sara@example.com",
            "password": "wrong-pass-123",
        },
        format="json",
    )

    assert response.status_code == 400


@pytest.mark.django_db
def test_logout_clears_session():
    User = get_user_model()
    User.objects.create_user(username="sara@example.com", password="strong-pass-123")
    client = APIClient()
    client.login(username="sara@example.com", password="strong-pass-123")

    response = client.post("/api/auth/logout/")
    me = client.get("/api/auth/me/")

    assert response.status_code == 204
    assert me.status_code == 403


@pytest.mark.django_db
def test_csrf_bootstrap_sets_csrf_cookie():
    client = APIClient(enforce_csrf_checks=True)

    response = client.get("/api/auth/csrf/")

    assert response.status_code == 204
    assert "csrftoken" in response.cookies
