# Generated for the medical appointment MVP.

from django.db import migrations, models
import django.db.models.deletion


class Migration(migrations.Migration):
    initial = True

    dependencies = [
        ("providers", "0001_initial"),
    ]

    operations = [
        migrations.CreateModel(
            name="MedicalService",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                (
                    "category",
                    models.CharField(
                        choices=[("visit", "Visit"), ("exam", "Exam")],
                        default="visit",
                        max_length=16,
                    ),
                ),
                ("name", models.CharField(max_length=160)),
                ("duration_minutes", models.PositiveIntegerField(default=30)),
                ("price", models.DecimalField(blank=True, decimal_places=2, max_digits=8, null=True)),
                ("is_active", models.BooleanField(default=True)),
                (
                    "specialty",
                    models.ForeignKey(
                        on_delete=django.db.models.deletion.PROTECT,
                        related_name="services",
                        to="providers.specialty",
                    ),
                ),
            ],
            options={
                "ordering": ["name"],
            },
        ),
        migrations.CreateModel(
            name="DoctorService",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                (
                    "doctor",
                    models.ForeignKey(
                        on_delete=django.db.models.deletion.CASCADE,
                        related_name="doctor_services",
                        to="providers.doctorprofile",
                    ),
                ),
                (
                    "service",
                    models.ForeignKey(
                        on_delete=django.db.models.deletion.CASCADE,
                        related_name="doctor_services",
                        to="services.medicalservice",
                    ),
                ),
            ],
            options={
                "unique_together": {("doctor", "service")},
            },
        ),
    ]
