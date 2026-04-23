from django.contrib import admin

from .models import DoctorService, MedicalService


@admin.register(MedicalService)
class MedicalServiceAdmin(admin.ModelAdmin):
    list_display = ("name", "category", "specialty", "duration_minutes", "price", "is_active")
    list_filter = ("category", "specialty", "is_active")
    search_fields = ("name", "specialty__name")


@admin.register(DoctorService)
class DoctorServiceAdmin(admin.ModelAdmin):
    list_display = ("doctor", "service")
    list_filter = ("doctor", "service")
    search_fields = ("doctor__display_name", "service__name")
