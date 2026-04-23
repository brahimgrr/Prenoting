from django.utils import timezone
from rest_framework import serializers

from scheduling.models import AvailabilitySlot


class AvailabilitySerializer(serializers.ModelSerializer):
    doctor_name = serializers.CharField(source="doctor.display_name", read_only=True)
    clinic_name = serializers.CharField(source="clinic.name", read_only=True)

    class Meta:
        model = AvailabilitySlot
        fields = [
            "id",
            "doctor",
            "doctor_name",
            "clinic",
            "clinic_name",
            "start_at",
            "end_at",
        ]


class DoctorAvailabilityCreateSerializer(serializers.ModelSerializer):
    class Meta:
        model = AvailabilitySlot
        fields = ["id", "clinic", "start_at", "end_at"]
        read_only_fields = ["id"]

    def validate(self, attrs):
        doctor = self.context["request"].user.doctor_profile
        start_at = attrs["start_at"]
        end_at = attrs["end_at"]

        if start_at <= timezone.now():
            raise serializers.ValidationError({
                "start_at": ["La disponibilità deve essere futura."],
            })

        if end_at <= start_at:
            raise serializers.ValidationError({
                "end_at": ["L'orario di fine deve essere successivo all'inizio."],
            })

        overlaps = AvailabilitySlot.objects.filter(
            doctor=doctor,
            start_at__lt=end_at,
            end_at__gt=start_at,
        )
        if overlaps.exists():
            raise serializers.ValidationError({
                "start_at": ["Esiste già una disponibilità sovrapposta."],
            })

        return attrs

    def create(self, validated_data):
        return AvailabilitySlot.objects.create(
            doctor=self.context["request"].user.doctor_profile,
            **validated_data,
        )
