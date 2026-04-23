# Generated for the medical appointment MVP.

from django.db import migrations, models
import django.db.models.deletion


class Migration(migrations.Migration):
    initial = True

    dependencies = [
        ("clinics", "0001_initial"),
        ("providers", "0001_initial"),
    ]

    operations = [
        migrations.CreateModel(
            name="AvailabilitySlot",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("start_at", models.DateTimeField()),
                ("end_at", models.DateTimeField()),
                ("is_blocked", models.BooleanField(default=False)),
                ("is_booked", models.BooleanField(default=False)),
                (
                    "clinic",
                    models.ForeignKey(
                        on_delete=django.db.models.deletion.PROTECT,
                        related_name="availability_slots",
                        to="clinics.cliniclocation",
                    ),
                ),
                (
                    "doctor",
                    models.ForeignKey(
                        on_delete=django.db.models.deletion.CASCADE,
                        related_name="availability_slots",
                        to="providers.doctorprofile",
                    ),
                ),
            ],
            options={
                "ordering": ["start_at"],
            },
        ),
        migrations.AddConstraint(
            model_name="availabilityslot",
            constraint=models.UniqueConstraint(fields=("doctor", "start_at"), name="unique_doctor_slot_start"),
        ),
    ]
