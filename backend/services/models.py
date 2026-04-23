from django.db import models

from providers.models import DoctorProfile, Specialty


class MedicalService(models.Model):
    class Category(models.TextChoices):
        VISIT = "visit", "Visit"
        EXAM = "exam", "Exam"

    name = models.CharField(max_length=160)
    category = models.CharField(
        max_length=16,
        choices=Category.choices,
        default=Category.VISIT,
    )
    specialty = models.ForeignKey(
        Specialty,
        on_delete=models.PROTECT,
        related_name="services",
    )
    duration_minutes = models.PositiveIntegerField(default=30)
    price = models.DecimalField(max_digits=8, decimal_places=2, null=True, blank=True)
    is_active = models.BooleanField(default=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name


class DoctorService(models.Model):
    doctor = models.ForeignKey(
        DoctorProfile,
        on_delete=models.CASCADE,
        related_name="doctor_services",
    )
    service = models.ForeignKey(
        MedicalService,
        on_delete=models.CASCADE,
        related_name="doctor_services",
    )

    class Meta:
        unique_together = ("doctor", "service")

    def __str__(self):
        return f"{self.doctor} - {self.service}"
