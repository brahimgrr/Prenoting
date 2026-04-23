from django.db import transaction
from django.shortcuts import get_object_or_404
from django.utils import timezone
from django.utils.dateparse import parse_date
from rest_framework import status, viewsets
from rest_framework.decorators import action
from rest_framework.exceptions import ValidationError
from rest_framework.permissions import BasePermission, IsAuthenticated
from rest_framework.response import Response
from rest_framework.views import APIView

from accounts.permissions import IsDoctor, IsStaffUser
from appointments.models import Appointment, AppointmentStatusHistory
from appointments.serializers import (
    ACTIVE_SLOT_STATUSES,
    AppointmentCancelSerializer,
    AppointmentCreateSerializer,
    AppointmentRescheduleSerializer,
    AppointmentSerializer,
    SLOT_UNAVAILABLE_ERROR,
    validate_slot_for_service,
)
from scheduling.models import AvailabilitySlot


APPOINTMENT_RESCHEDULED_AUDIT_STATUS = "rescheduled"
DOCTOR_STATUS_CHOICES = {
    Appointment.Status.CHECKED_IN,
    Appointment.Status.COMPLETED,
    Appointment.Status.NO_SHOW,
}
APPOINTMENT_STATUS_CHOICES = {choice.value for choice in Appointment.Status}
DOCTOR_STATUS_TRANSITIONS = {
    Appointment.Status.CONFIRMED: {
        Appointment.Status.CHECKED_IN,
        Appointment.Status.NO_SHOW,
    },
    Appointment.Status.CHECKED_IN: {Appointment.Status.COMPLETED},
}
STAFF_STATUS_TRANSITIONS = {
    Appointment.Status.CONFIRMED: {
        Appointment.Status.CHECKED_IN,
        Appointment.Status.COMPLETED,
        Appointment.Status.CANCELLED,
        Appointment.Status.NO_SHOW,
    },
    Appointment.Status.CHECKED_IN: {
        Appointment.Status.COMPLETED,
        Appointment.Status.CANCELLED,
        Appointment.Status.NO_SHOW,
    },
}


def validate_confirmed_future_appointment(appointment, action):
    if appointment.status != Appointment.Status.CONFIRMED:
        raise ValidationError(
            {"status": [f"Only confirmed appointments can be {action}."]},
        )
    if appointment.start_at <= timezone.now():
        raise ValidationError(
            {"start_at": [f"Past appointments cannot be {action}."]},
        )


def appointment_queryset():
    return Appointment.objects.select_related(
        "patient",
        "patient__user",
        "doctor",
        "doctor__user",
        "service",
        "clinic",
        "slot",
    )


def validate_status_choice(next_status, allowed_statuses):
    if next_status not in allowed_statuses:
        raise ValidationError({"status": ["Select a valid status."]})


def validate_status_transition(appointment, next_status, transitions):
    if appointment.status == next_status:
        return

    if next_status not in transitions.get(appointment.status, set()):
        raise ValidationError({"status": ["This status transition is not allowed."]})


def free_slot_if_future_cancelled(appointment, next_status):
    if (
        next_status != Appointment.Status.CANCELLED
        or appointment.start_at <= timezone.now()
    ):
        return

    slot = AvailabilitySlot.objects.select_for_update().get(pk=appointment.slot_id)
    if slot.is_booked:
        slot.is_booked = False
        slot.save(update_fields=["is_booked"])


def update_appointment_status(appointment, next_status, changed_by, transitions):
    validate_status_transition(appointment, next_status, transitions)

    previous_status = appointment.status
    if previous_status == next_status:
        return appointment

    free_slot_if_future_cancelled(appointment, next_status)
    appointment.status = next_status
    appointment.save(update_fields=["status", "updated_at"])
    AppointmentStatusHistory.objects.create(
        appointment=appointment,
        previous_status=previous_status,
        new_status=next_status,
        changed_by=changed_by,
    )
    return appointment


def parse_integer_filter(value, field_name):
    try:
        return int(value)
    except (TypeError, ValueError) as exc:
        raise ValidationError({field_name: ["A valid integer is required."]}) from exc


def apply_staff_filters(queryset, params):
    filter_map = {
        "clinic": "clinic_id",
        "doctor": "doctor_id",
        "service": "service_id",
    }
    for param_name, field_name in filter_map.items():
        value = params.get(param_name)
        if value:
            queryset = queryset.filter(
                **{field_name: parse_integer_filter(value, param_name)},
            )

    date_value = params.get("date")
    if date_value:
        parsed_date = parse_date(date_value)
        if parsed_date is None:
            raise ValidationError({"date": ["Use YYYY-MM-DD format."]})
        queryset = queryset.filter(start_at__date=parsed_date)

    status_value = params.get("status")
    if status_value:
        validate_status_choice(status_value, APPOINTMENT_STATUS_CHOICES)
        queryset = queryset.filter(status=status_value)

    return queryset


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
            validate_confirmed_future_appointment(appointment, "cancelled")
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
        validate_confirmed_future_appointment(appointment, "rescheduled")
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
            validate_confirmed_future_appointment(locked_appointment, "rescheduled")
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
            if (
                Appointment.objects.filter(slot=new_slot, status__in=ACTIVE_SLOT_STATUSES)
                .exclude(pk=locked_appointment.pk)
                .exists()
            ):
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
                new_status=APPOINTMENT_RESCHEDULED_AUDIT_STATUS,
                changed_by=request.user,
            )

        output = AppointmentSerializer(
            locked_appointment,
            context=self.get_serializer_context(),
        )
        return Response(output.data)


class DoctorScheduleAPIView(APIView):
    permission_classes = [IsAuthenticated, IsDoctor]

    def get(self, request):
        queryset = (
            appointment_queryset()
            .filter(doctor__user=request.user)
            .order_by("start_at")
        )

        date_value = request.query_params.get("date")
        if date_value:
            parsed_date = parse_date(date_value)
            if parsed_date is None:
                raise ValidationError({"date": ["Use YYYY-MM-DD format."]})
            queryset = queryset.filter(start_at__date=parsed_date)

        status_value = request.query_params.get("status")
        if status_value:
            validate_status_choice(status_value, APPOINTMENT_STATUS_CHOICES)
            queryset = queryset.filter(status=status_value)

        serializer = AppointmentSerializer(
            queryset,
            many=True,
            context={"request": request},
        )
        return Response(serializer.data)


class DoctorAppointmentStatusAPIView(APIView):
    permission_classes = [IsAuthenticated, IsDoctor]

    def post(self, request, pk):
        next_status = request.data.get("status")
        validate_status_choice(next_status, DOCTOR_STATUS_CHOICES)

        with transaction.atomic():
            appointment = get_object_or_404(
                appointment_queryset().select_for_update(),
                pk=pk,
                doctor__user=request.user,
            )
            update_appointment_status(
                appointment,
                next_status,
                request.user,
                DOCTOR_STATUS_TRANSITIONS,
            )

        serializer = AppointmentSerializer(appointment, context={"request": request})
        return Response(serializer.data)


class StaffAppointmentListAPIView(APIView):
    permission_classes = [IsAuthenticated, IsStaffUser]

    def get(self, request):
        queryset = apply_staff_filters(
            appointment_queryset().order_by("start_at"),
            request.query_params,
        )
        serializer = AppointmentSerializer(
            queryset,
            many=True,
            context={"request": request},
        )
        return Response(serializer.data)


class StaffAppointmentStatusAPIView(APIView):
    permission_classes = [IsAuthenticated, IsStaffUser]

    def post(self, request, pk):
        next_status = request.data.get("status")
        validate_status_choice(next_status, APPOINTMENT_STATUS_CHOICES)

        with transaction.atomic():
            appointment = get_object_or_404(
                appointment_queryset().select_for_update(),
                pk=pk,
            )
            update_appointment_status(
                appointment,
                next_status,
                request.user,
                STAFF_STATUS_TRANSITIONS,
            )

        serializer = AppointmentSerializer(appointment, context={"request": request})
        return Response(serializer.data)
