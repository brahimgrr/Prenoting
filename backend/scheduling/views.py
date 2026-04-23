from django.utils import timezone
from django.utils.dateparse import parse_date
from rest_framework.generics import ListAPIView
from rest_framework.permissions import AllowAny

from scheduling.models import AvailabilitySlot
from scheduling.serializers import AvailabilitySerializer


class AvailabilityListView(ListAPIView):
    serializer_class = AvailabilitySerializer
    permission_classes = [AllowAny]

    def get_queryset(self):
        queryset = (
            AvailabilitySlot.objects.filter(
                is_blocked=False,
                is_booked=False,
                start_at__gte=timezone.now(),
            )
            .select_related("doctor", "clinic", "doctor__specialty")
            .order_by("start_at")
        )
        doctor = self.request.query_params.get("doctor")
        clinic = self.request.query_params.get("clinic")
        service = self.request.query_params.get("service")
        specialty = self.request.query_params.get("specialty")
        date = self.request.query_params.get("date")

        if doctor:
            queryset = queryset.filter(doctor_id=doctor)
        if clinic:
            queryset = queryset.filter(clinic_id=clinic)
        if service:
            queryset = queryset.filter(doctor__doctor_services__service_id=service)
        if specialty:
            queryset = queryset.filter(doctor__specialty_id=specialty)
        if date:
            parsed_date = parse_date(date)
            if parsed_date:
                queryset = queryset.filter(start_at__date=parsed_date)

        return queryset.distinct()
