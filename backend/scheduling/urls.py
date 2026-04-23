from django.urls import path

from scheduling.views import AvailabilityListView


urlpatterns = [
    path("", AvailabilityListView.as_view(), name="availability-list"),
]
