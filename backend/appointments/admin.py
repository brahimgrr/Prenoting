from django.contrib import admin

from .models import Appointment, AppointmentStatusHistory


@admin.register(Appointment)
class AppointmentAdmin(admin.ModelAdmin):
    list_display = ("service", "doctor", "patient", "clinic", "start_at", "status")
    list_filter = ("status", "clinic", "doctor", "service")
    search_fields = ("patient__user__username", "doctor__display_name", "service__name")


@admin.register(AppointmentStatusHistory)
class AppointmentStatusHistoryAdmin(admin.ModelAdmin):
    list_display = ("appointment", "previous_status", "new_status", "changed_by", "changed_at")
    list_filter = ("new_status",)
