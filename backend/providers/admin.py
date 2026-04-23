from django.contrib import admin

from .models import DoctorProfile, Specialty


@admin.register(Specialty)
class SpecialtyAdmin(admin.ModelAdmin):
    list_display = ("name", "description")
    search_fields = ("name", "description")


@admin.register(DoctorProfile)
class DoctorProfileAdmin(admin.ModelAdmin):
    list_display = ("display_name", "specialty", "user", "license_number", "is_active")
    list_filter = ("specialty", "is_active")
    search_fields = ("display_name", "user__username", "license_number")
