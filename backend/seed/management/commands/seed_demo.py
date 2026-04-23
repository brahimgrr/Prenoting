from datetime import timedelta

from django.contrib.auth import get_user_model
from django.contrib.auth.models import Group
from django.core.management.base import BaseCommand
from django.utils import timezone

from accounts.models import PatientProfile
from clinics.models import ClinicLocation
from providers.models import DoctorProfile, Specialty
from scheduling.models import AvailabilitySlot
from services.models import DoctorService, MedicalService


class Command(BaseCommand):
    help = "Seed demo medical appointment data."

    def handle(self, *args, **options):
        User = get_user_model()

        admin, _ = User.objects.update_or_create(
            username="admin",
            defaults={
                "email": "admin@example.com",
                "first_name": "Admin",
                "last_name": "User",
                "is_staff": True,
                "is_superuser": True,
            },
        )
        admin.set_password("admin123")
        admin.save()

        staff, _ = User.objects.update_or_create(
            username="staff",
            defaults={
                "email": "staff@example.com",
                "first_name": "Staff",
                "last_name": "User",
                "is_staff": True,
            },
        )
        staff.set_password("staff123")
        staff.save()
        staff_group, _ = Group.objects.get_or_create(name="Staff")
        staff.groups.add(staff_group)

        patient_user, _ = User.objects.update_or_create(
            username="patient",
            defaults={
                "email": "patient@example.com",
                "first_name": "Pat",
                "last_name": "Ient",
            },
        )
        patient_user.set_password("patient123")
        patient_user.save()
        PatientProfile.objects.update_or_create(
            user=patient_user,
            defaults={
                "phone": "555-0100",
                "address": "10 Patient Way",
                "identity_code": "PAT-001",
            },
        )

        specialties = {}
        for name, description in (
            ("Cardiology", "Heart and vascular care"),
            ("Dermatology", "Skin health and treatment"),
            ("Radiology", "Diagnostic imaging"),
        ):
            specialties[name], _ = Specialty.objects.update_or_create(
                name=name,
                defaults={"description": description},
            )

        doctor_specs = (
            ("doctor.heart", "Amelia", "Heart", "Dr. Amelia Heart", "Cardiology", "CARD-001"),
            ("doctor.skin", "Dorian", "Skin", "Dr. Dorian Skin", "Dermatology", "DERM-001"),
        )
        doctors = []
        for username, first_name, last_name, display_name, specialty_name, license_number in doctor_specs:
            doctor_user, _ = User.objects.update_or_create(
                username=username,
                defaults={
                    "email": f"{username}@example.com",
                    "first_name": first_name,
                    "last_name": last_name,
                    "is_staff": True,
                },
            )
            doctor_user.set_password("doctor123")
            doctor_user.save()
            doctor, _ = DoctorProfile.objects.update_or_create(
                user=doctor_user,
                defaults={
                    "display_name": display_name,
                    "specialty": specialties[specialty_name],
                    "license_number": license_number,
                    "is_active": True,
                },
            )
            doctors.append(doctor)

        clinics = {}
        for name, address, phone in (
            ("Downtown Clinic", "1 Main St", "555-1000"),
            ("Northside Medical Center", "200 North Ave", "555-2000"),
        ):
            clinics[name], _ = ClinicLocation.objects.update_or_create(
                name=name,
                defaults={"address": address, "phone": phone, "is_active": True},
            )

        service_specs = (
            ("Cardiology consultation", MedicalService.Category.VISIT, "Cardiology", 30, "150.00"),
            ("ECG exam", MedicalService.Category.EXAM, "Cardiology", 20, "80.00"),
            ("Dermatology consultation", MedicalService.Category.VISIT, "Dermatology", 30, "120.00"),
            ("Ultrasound exam", MedicalService.Category.EXAM, "Radiology", 45, "200.00"),
        )
        services = {}
        for name, category, specialty_name, duration, price in service_specs:
            services[name], _ = MedicalService.objects.update_or_create(
                name=name,
                defaults={
                    "category": category,
                    "specialty": specialties[specialty_name],
                    "duration_minutes": duration,
                    "price": price,
                    "is_active": True,
                },
            )

        doctor_services = {
            "Dr. Amelia Heart": ("Cardiology consultation", "ECG exam", "Ultrasound exam"),
            "Dr. Dorian Skin": ("Dermatology consultation",),
        }
        for doctor in doctors:
            for service_name in doctor_services[doctor.display_name]:
                DoctorService.objects.get_or_create(doctor=doctor, service=services[service_name])

        base = timezone.now().replace(hour=9, minute=0, second=0, microsecond=0)
        clinic_values = list(clinics.values())
        for day_offset in range(1, 8):
            for doctor_index, doctor in enumerate(doctors):
                for hour_offset in range(3):
                    start_at = base + timedelta(days=day_offset, hours=doctor_index * 3 + hour_offset)
                    AvailabilitySlot.objects.update_or_create(
                        doctor=doctor,
                        start_at=start_at,
                        defaults={
                            "clinic": clinic_values[(doctor_index + hour_offset) % len(clinic_values)],
                            "end_at": start_at + timedelta(minutes=30),
                            "is_blocked": False,
                            "is_booked": False,
                        },
                    )

        self.stdout.write("Demo data seeded")
