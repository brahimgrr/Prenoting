from rest_framework.permissions import BasePermission


def user_role(user):
    if not user or not user.is_authenticated:
        return "anonymous"
    if user.is_superuser:
        return "user"
    if user.groups.filter(name="Staff").exists():
        return "staff"
    if hasattr(user, "doctor_profile"):
        return "doctor"
    if hasattr(user, "patient_profile"):
        return "patient"
    return "user"


class IsPatient(BasePermission):
    def has_permission(self, request, view):
        return user_role(request.user) == "patient"


class IsDoctor(BasePermission):
    def has_permission(self, request, view):
        return user_role(request.user) == "doctor"


class IsStaffUser(BasePermission):
    def has_permission(self, request, view):
        return user_role(request.user) == "staff"
