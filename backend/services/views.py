from rest_framework.generics import ListAPIView
from rest_framework.exceptions import ValidationError
from rest_framework.permissions import AllowAny

from services.models import MedicalService
from services.serializers import ServiceSerializer


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


class ServiceListView(ListAPIView):
    serializer_class = ServiceSerializer
    permission_classes = [AllowAny]

    def get_queryset(self):
        queryset = MedicalService.objects.filter(is_active=True).select_related("specialty")
        search = self.request.query_params.get("search")
        specialty = _parse_id_param(self.request.query_params, "specialty")
        category = self.request.query_params.get("category")

        if search:
            queryset = queryset.filter(name__icontains=search)
        if specialty is not None:
            queryset = queryset.filter(specialty_id=specialty)
        if category:
            queryset = queryset.filter(category=category)

        return queryset
