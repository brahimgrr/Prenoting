from django.db import transaction
from django.utils import timezone
from rest_framework import serializers

from accounts.models import PatientProfile
from appointments.models import Appointment
from scheduling.models import AvailabilitySlot
from services.models import DoctorService, MedicalService


SLOT_UNAVAILABLE_ERROR = {"slot": ["This slot is no longer available."]}


def validate_slot_for_service(slot, service):
    errors = {}
    if not service.is_active:
        errors["service"] = ["This service is not active."]
    if not slot.doctor.is_active:
        errors["slot"] = ["This doctor is not active."]
    if not slot.clinic.is_active:
        errors["slot"] = ["This clinic is not active."]
    if slot.is_blocked or slot.is_booked:
        errors["slot"] = SLOT_UNAVAILABLE_ERROR["slot"]
    if slot.start_at <= timezone.now():
        errors["slot"] = ["This slot is no longer available."]
    if not DoctorService.objects.filter(doctor=slot.doctor, service=service).exists():
        errors["service"] = ["Selected doctor does not offer this service."]

    if errors:
        raise serializers.ValidationError(errors)


class AppointmentSerializer(serializers.ModelSerializer):
    patient_name = serializers.SerializerMethodField()
    doctor_name = serializers.CharField(source="doctor.display_name", read_only=True)
    service_name = serializers.CharField(source="service.name", read_only=True)
    clinic_name = serializers.CharField(source="clinic.name", read_only=True)

    def get_patient_name(self, appointment):
        user = appointment.patient.user
        return user.get_full_name() or user.username

    class Meta:
        model = Appointment
        fields = [
            "id",
            "patient",
            "patient_name",
            "doctor",
            "doctor_name",
            "service",
            "service_name",
            "clinic",
            "clinic_name",
            "slot",
            "start_at",
            "end_at",
            "status",
            "notes",
            "cancellation_reason",
        ]
        read_only_fields = fields


class AppointmentCreateSerializer(serializers.ModelSerializer):
    slot = serializers.PrimaryKeyRelatedField(
        queryset=AvailabilitySlot.objects.select_related("doctor", "clinic"),
    )
    service = serializers.PrimaryKeyRelatedField(queryset=MedicalService.objects.all())
    notes = serializers.CharField(required=False, allow_blank=True)

    class Meta:
        model = Appointment
        fields = ["slot", "service", "notes"]

    def validate(self, attrs):
        validate_slot_for_service(attrs["slot"], attrs["service"])
        return attrs

    def create(self, validated_data):
        request = self.context["request"]
        try:
            patient = request.user.patient_profile
        except PatientProfile.DoesNotExist as exc:
            raise serializers.ValidationError(
                {"patient": ["Authenticated user does not have a patient profile."]},
            ) from exc

        slot_id = validated_data["slot"].id
        service = validated_data["service"]
        notes = validated_data.get("notes", "")

        with transaction.atomic():
            locked_slot = (
                AvailabilitySlot.objects.select_for_update()
                .select_related("doctor", "clinic")
                .get(id=slot_id)
            )
            if locked_slot.is_booked or locked_slot.is_blocked:
                raise serializers.ValidationError(SLOT_UNAVAILABLE_ERROR)
            if Appointment.objects.filter(slot=locked_slot).exists():
                raise serializers.ValidationError(SLOT_UNAVAILABLE_ERROR)
            validate_slot_for_service(locked_slot, service)

            appointment = Appointment.objects.create(
                patient=patient,
                doctor=locked_slot.doctor,
                service=service,
                clinic=locked_slot.clinic,
                slot=locked_slot,
                start_at=locked_slot.start_at,
                end_at=locked_slot.end_at,
                status=Appointment.Status.CONFIRMED,
                notes=notes,
            )
            locked_slot.is_booked = True
            locked_slot.save(update_fields=["is_booked"])

        return appointment


class AppointmentCancelSerializer(serializers.Serializer):
    cancellation_reason = serializers.CharField(required=False, allow_blank=True)


class AppointmentRescheduleSerializer(serializers.Serializer):
    slot = serializers.PrimaryKeyRelatedField(
        queryset=AvailabilitySlot.objects.select_related("doctor", "clinic"),
    )

    def validate_slot(self, slot):
        appointment = self.context["appointment"]
        validate_slot_for_service(slot, appointment.service)
        if slot.id == appointment.slot_id:
            raise serializers.ValidationError("Select a different slot.")
        return slot
