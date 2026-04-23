from django.contrib import admin

from .models import ClinicLocation


@admin.register(ClinicLocation)
class ClinicLocationAdmin(admin.ModelAdmin):
    list_display = ("name", "address", "phone", "is_active")
    list_filter = ("is_active",)
    search_fields = ("name", "address", "phone")
