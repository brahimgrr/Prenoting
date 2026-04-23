from rest_framework.generics import ListAPIView
from rest_framework.exceptions import ValidationError
from rest_framework.permissions import AllowAny

from providers.models import DoctorProfile
from providers.serializers import DoctorSerializer


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


class DoctorListView(ListAPIView):
    serializer_class = DoctorSerializer
    permission_classes = [AllowAny]

    def get_queryset(self):
        queryset = DoctorProfile.objects.filter(is_active=True).select_related("specialty")
        search = self.request.query_params.get("search")
        specialty = _parse_id_param(self.request.query_params, "specialty")

        if search:
            queryset = queryset.filter(display_name__icontains=search)
        if specialty is not None:
            queryset = queryset.filter(specialty_id=specialty)

        return queryset
