from django.utils import timezone
from django.utils.dateparse import parse_date
from rest_framework.generics import ListAPIView
from rest_framework.exceptions import ValidationError
from rest_framework.permissions import AllowAny
from rest_framework.response import Response
from rest_framework.views import APIView

from accounts.permissions import IsDoctor
from scheduling.models import AvailabilitySlot
from scheduling.serializers import AvailabilitySerializer, DoctorAvailabilityCreateSerializer


def _parse_id_param(query_params, field_name):
    value = query_params.get(field_name)
    if value is None:
        return None

    try:
        parsed_value = int(value)
    except (TypeError, ValueError):
        raise ValidationError({field_name: ["Usa un ID numerico valido."]})

    if parsed_value <= 0:
        raise ValidationError({field_name: ["Usa un ID numerico valido."]})

    return parsed_value


def _parse_date_param(query_params, field_name):
    value = query_params.get(field_name)
    if value is None:
        return None

    try:
        parsed_value = parse_date(value)
    except ValueError:
        parsed_value = None

    if parsed_value is None:
        raise ValidationError({field_name: ["Usa il formato AAAA-MM-GG."]})

    return parsed_value


class AvailabilityListView(ListAPIView):
    serializer_class = AvailabilitySerializer
    permission_classes = [AllowAny]

    def get_queryset(self):
        queryset = (
            AvailabilitySlot.objects.filter(
                doctor__is_active=True,
                clinic__is_active=True,
                is_blocked=False,
                is_booked=False,
                start_at__gte=timezone.now(),
            )
            .select_related("doctor", "clinic", "doctor__specialty")
            .order_by("start_at")
        )
        doctor = _parse_id_param(self.request.query_params, "doctor")
        clinic = _parse_id_param(self.request.query_params, "clinic")
        service = _parse_id_param(self.request.query_params, "service")
        specialty = _parse_id_param(self.request.query_params, "specialty")
        date = _parse_date_param(self.request.query_params, "date")

        if doctor is not None:
            queryset = queryset.filter(doctor_id=doctor)
        if clinic is not None:
            queryset = queryset.filter(clinic_id=clinic)
        if service is not None:
            queryset = queryset.filter(
                doctor__doctor_services__service_id=service,
                doctor__doctor_services__service__is_active=True,
            )
        if specialty is not None:
            queryset = queryset.filter(doctor__specialty_id=specialty)
        if date is not None:
            queryset = queryset.filter(start_at__date=date)

        return queryset.distinct()


class DoctorAvailabilityCreateView(APIView):
    permission_classes = [IsDoctor]

    def post(self, request):
        serializer = DoctorAvailabilityCreateSerializer(
            data=request.data,
            context={"request": request},
        )
        serializer.is_valid(raise_exception=True)
        slot = serializer.save()
        output = AvailabilitySerializer(slot)
        return Response(output.data, status=201)
