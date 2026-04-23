from rest_framework import serializers

from services.models import MedicalService


class ServiceSerializer(serializers.ModelSerializer):
    specialty_name = serializers.CharField(source="specialty.name", read_only=True)

    class Meta:
        model = MedicalService
        fields = [
            "id",
            "name",
            "category",
            "specialty",
            "specialty_name",
            "duration_minutes",
            "price",
        ]
