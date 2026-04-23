from rest_framework.generics import ListAPIView
from rest_framework.permissions import AllowAny

from providers.models import DoctorProfile
from providers.serializers import DoctorSerializer


class DoctorListView(ListAPIView):
    serializer_class = DoctorSerializer
    permission_classes = [AllowAny]

    def get_queryset(self):
        queryset = DoctorProfile.objects.filter(is_active=True).select_related("specialty")
        search = self.request.query_params.get("search")
        specialty = self.request.query_params.get("specialty")

        if search:
            queryset = queryset.filter(display_name__icontains=search)
        if specialty:
            queryset = queryset.filter(specialty_id=specialty)

        return queryset
