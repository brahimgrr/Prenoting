from django.urls import path
from rest_framework.routers import DefaultRouter

from appointments.views import (
    AppointmentViewSet,
    DoctorAppointmentStatusAPIView,
    DoctorScheduleAPIView,
    StaffAppointmentListAPIView,
    StaffAppointmentStatusAPIView,
)


router = DefaultRouter()
router.register("", AppointmentViewSet, basename="appointment")

urlpatterns = [
    path("doctor/schedule/", DoctorScheduleAPIView.as_view(), name="doctor-schedule"),
    path(
        "doctor/<int:pk>/status/",
        DoctorAppointmentStatusAPIView.as_view(),
        name="doctor-appointment-status",
    ),
    path("staff/", StaffAppointmentListAPIView.as_view(), name="staff-appointments"),
    path(
        "staff/<int:pk>/status/",
        StaffAppointmentStatusAPIView.as_view(),
        name="staff-appointment-status",
    ),
]

urlpatterns += router.urls
