from django.conf import settings


def test_cors_allows_credentials_for_session_auth():
    assert settings.CORS_ALLOW_CREDENTIALS is True
