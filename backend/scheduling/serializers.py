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
