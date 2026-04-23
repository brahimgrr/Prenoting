from rest_framework import serializers

from providers.models import DoctorProfile


class DoctorSerializer(serializers.ModelSerializer):
    specialty_name = serializers.CharField(source="specialty.name", read_only=True)

    class Meta:
        model = DoctorProfile
        fields = [
            "id",
            "display_name",
            "specialty",
            "specialty_name",
            "bio",
            "license_number",
        ]
