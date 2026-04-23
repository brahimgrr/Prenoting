from django.urls import path

from providers.views import DoctorListView


urlpatterns = [
    path("", DoctorListView.as_view(), name="doctor-list"),
]
