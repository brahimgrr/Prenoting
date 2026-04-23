from django.contrib import admin

from .models import PatientProfile


@admin.register(PatientProfile)
class PatientProfileAdmin(admin.ModelAdmin):
    list_display = ("user", "phone", "date_of_birth", "gender")
    list_filter = ("gender",)
    search_fields = (
        "user__username",
        "user__first_name",
        "user__last_name",
        "phone",
        "identity_code",
    )
