from django.contrib import admin

from .models import AvailabilitySlot


@admin.register(AvailabilitySlot)
class AvailabilitySlotAdmin(admin.ModelAdmin):
    list_display = ("doctor", "clinic", "start_at", "end_at", "is_blocked", "is_booked")
    list_filter = ("clinic", "doctor", "is_blocked", "is_booked")
    search_fields = ("doctor__display_name", "clinic__name")
