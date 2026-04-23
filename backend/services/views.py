from rest_framework.generics import ListAPIView
from rest_framework.permissions import AllowAny

from services.models import MedicalService
from services.serializers import ServiceSerializer


class ServiceListView(ListAPIView):
    serializer_class = ServiceSerializer
    permission_classes = [AllowAny]

    def get_queryset(self):
        queryset = MedicalService.objects.filter(is_active=True).select_related("specialty")
        search = self.request.query_params.get("search")
        specialty = self.request.query_params.get("specialty")
        category = self.request.query_params.get("category")

        if search:
            queryset = queryset.filter(name__icontains=search)
        if specialty:
            queryset = queryset.filter(specialty_id=specialty)
        if category:
            queryset = queryset.filter(category=category)

        return queryset
