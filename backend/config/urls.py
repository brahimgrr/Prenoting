from django.contrib import admin
from django.urls import include, path


urlpatterns = [
    path("admin/", admin.site.urls),
    path("api/auth/", include("accounts.urls")),
    path("api/doctors/", include("providers.urls")),
    path("api/services/", include("services.urls")),
    path("api/availability/", include("scheduling.urls")),
    path("api/appointments/", include("appointments.urls")),
]
