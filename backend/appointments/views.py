from django.db import transaction
from django.shortcuts import get_object_or_404
from rest_framework import status, viewsets
from rest_framework.decorators import action
from rest_framework.permissions import BasePermission, IsAuthenticated
from rest_framework.response import Response
from rest_framework.exceptions import ValidationError

from appointments.models import Appointment, AppointmentStatusHistory
from appointments.serializers import (
    AppointmentCancelSerializer,
    AppointmentCreateSerializer,
    AppointmentRescheduleSerializer,
    AppointmentSerializer,
    SLOT_UNAVAILABLE_ERROR,
    validate_slot_for_service,
)
from scheduling.models import AvailabilitySlot


class IsPatientUser(BasePermission):
    message = "Authenticated user does not have a patient profile."

    def has_permission(self, request, view):
        return (
            request.user
            and request.user.is_authenticated
            and hasattr(request.user, "patient_profile")
        )


class AppointmentViewSet(viewsets.ModelViewSet):
    permission_classes = [IsAuthenticated, IsPatientUser]
    http_method_names = ["get", "post", "head", "options"]

    def get_queryset(self):
        if not (
            self.request.user
            and self.request.user.is_authenticated
            and hasattr(self.request.user, "patient_profile")
        ):
            return Appointment.objects.none()

        return (
            Appointment.objects.filter(patient=self.request.user.patient_profile)
            .select_related("patient", "doctor", "service", "clinic", "slot")
            .order_by("-start_at")
        )

    def get_serializer_class(self):
        if self.action == "create":
            return AppointmentCreateSerializer
        if self.action == "cancel":
            return AppointmentCancelSerializer
        if self.action == "reschedule":
            return AppointmentRescheduleSerializer
        return AppointmentSerializer

    def create(self, request, *args, **kwargs):
        serializer = self.get_serializer(data=request.data)
        serializer.is_valid(raise_exception=True)
        appointment = serializer.save()
        output = AppointmentSerializer(appointment, context=self.get_serializer_context())
        return Response(output.data, status=status.HTTP_201_CREATED)

    @action(detail=True, methods=["post"])
    def cancel(self, request, pk=None):
        serializer = self.get_serializer(data=request.data)
        serializer.is_valid(raise_exception=True)

        with transaction.atomic():
            appointment = get_object_or_404(
                Appointment.objects.select_for_update().select_related("slot"),
                pk=pk,
                patient=request.user.patient_profile,
            )
            slot = AvailabilitySlot.objects.select_for_update().get(pk=appointment.slot_id)
            previous_status = appointment.status

            appointment.status = Appointment.Status.CANCELLED
            appointment.cancellation_reason = serializer.validated_data.get(
                "cancellation_reason",
                "",
            )
            appointment.save(update_fields=["status", "cancellation_reason", "updated_at"])

            slot.is_booked = False
            slot.save(update_fields=["is_booked"])

            AppointmentStatusHistory.objects.create(
                appointment=appointment,
                previous_status=previous_status,
                new_status=Appointment.Status.CANCELLED,
                changed_by=request.user,
            )

        output = AppointmentSerializer(appointment, context=self.get_serializer_context())
        return Response(output.data)

    @action(detail=True, methods=["post"])
    def reschedule(self, request, pk=None):
        appointment = self.get_object()
        serializer = self.get_serializer(
            data=request.data,
            context={**self.get_serializer_context(), "appointment": appointment},
        )
        serializer.is_valid(raise_exception=True)
        new_slot_id = serializer.validated_data["slot"].id

        with transaction.atomic():
            locked_appointment = get_object_or_404(
                Appointment.objects.select_for_update().select_related("service"),
                pk=pk,
                patient=request.user.patient_profile,
            )
            if new_slot_id == locked_appointment.slot_id:
                raise ValidationError({"slot": ["Select a different slot."]})

            old_slot = AvailabilitySlot.objects.select_for_update().get(
                pk=locked_appointment.slot_id,
            )
            new_slot = (
                AvailabilitySlot.objects.select_for_update()
                .select_related("doctor", "clinic")
                .get(pk=new_slot_id)
            )
            if new_slot.is_booked or new_slot.is_blocked:
                raise ValidationError(SLOT_UNAVAILABLE_ERROR)
            validate_slot_for_service(new_slot, locked_appointment.service)
            if Appointment.objects.filter(slot=new_slot).exclude(pk=locked_appointment.pk).exists():
                raise ValidationError(SLOT_UNAVAILABLE_ERROR)

            previous_status = locked_appointment.status
            old_slot.is_booked = False
            old_slot.save(update_fields=["is_booked"])
            new_slot.is_booked = True
            new_slot.save(update_fields=["is_booked"])

            locked_appointment.doctor = new_slot.doctor
            locked_appointment.clinic = new_slot.clinic
            locked_appointment.slot = new_slot
            locked_appointment.start_at = new_slot.start_at
            locked_appointment.end_at = new_slot.end_at
            locked_appointment.status = Appointment.Status.CONFIRMED
            locked_appointment.cancellation_reason = ""
            locked_appointment.save(
                update_fields=[
                    "doctor",
                    "clinic",
                    "slot",
                    "start_at",
                    "end_at",
                    "status",
                    "cancellation_reason",
                    "updated_at",
                ],
            )

            AppointmentStatusHistory.objects.create(
                appointment=locked_appointment,
                previous_status=previous_status,
                new_status=locked_appointment.status,
                changed_by=request.user,
            )

        output = AppointmentSerializer(
            locked_appointment,
            context=self.get_serializer_context(),
        )
        return Response(output.data)
