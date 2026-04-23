from rest_framework import serializers

from providers.models import DoctorProfile
from services.models import DoctorService


class DoctorSerializer(serializers.ModelSerializer):
    specialty_name = serializers.CharField(source="specialty.name", read_only=True)
    service_ids = serializers.SerializerMethodField()

    def get_service_ids(self, doctor):
        return list(
            DoctorService.objects.filter(
                doctor=doctor,
                service__is_active=True,
            ).values_list("service_id", flat=True),
        )

    class Meta:
        model = DoctorProfile
        fields = [
            "id",
            "display_name",
            "specialty",
            "specialty_name",
            "bio",
            "license_number",
            "service_ids",
        ]
