from django.urls import path

from scheduling.views import AvailabilityListView, DoctorAvailabilityCreateView


urlpatterns = [
    path("doctor/", DoctorAvailabilityCreateView.as_view(), name="doctor-availability-create"),
    path("", AvailabilityListView.as_view(), name="availability-list"),
]
