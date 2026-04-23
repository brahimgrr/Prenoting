from django.db import models

from clinics.models import ClinicLocation
from providers.models import DoctorProfile


class AvailabilitySlot(models.Model):
    doctor = models.ForeignKey(
        DoctorProfile,
        on_delete=models.CASCADE,
        related_name="availability_slots",
    )
    clinic = models.ForeignKey(
        ClinicLocation,
        on_delete=models.PROTECT,
        related_name="availability_slots",
    )
    start_at = models.DateTimeField()
    end_at = models.DateTimeField()
    is_blocked = models.BooleanField(default=False)
    is_booked = models.BooleanField(default=False)

    class Meta:
        ordering = ["start_at"]
        constraints = [
            models.UniqueConstraint(
                fields=["doctor", "start_at"],
                name="unique_doctor_slot_start",
            ),
        ]

    def __str__(self):
        return f"{self.doctor} {self.start_at:%Y-%m-%d %H:%M}"
