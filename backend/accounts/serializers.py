from django.contrib.auth import get_user_model
from django.db import transaction
from rest_framework import serializers

from accounts.models import PatientProfile
from accounts.permissions import user_role


class UserSerializer(serializers.ModelSerializer):
    role = serializers.SerializerMethodField()

    class Meta:
        model = get_user_model()
        fields = ["id", "username", "first_name", "last_name", "role"]

    def get_role(self, user):
        return user_role(user)


class RegisterSerializer(serializers.Serializer):
    username = serializers.CharField(max_length=150)
    password = serializers.CharField(write_only=True)
    first_name = serializers.CharField(max_length=150, required=False, allow_blank=True)
    last_name = serializers.CharField(max_length=150, required=False, allow_blank=True)
    phone = serializers.CharField(max_length=32)

    def validate_username(self, value):
        User = get_user_model()
        if User.objects.filter(username=value).exists():
            raise serializers.ValidationError("Esiste già un utente con questo username.")
        return value

    def validate_password(self, value):
        if len(value) < 8:
            raise serializers.ValidationError("La password deve contenere almeno 8 caratteri.")
        return value

    @transaction.atomic
    def create(self, validated_data):
        phone = validated_data.pop("phone")
        User = get_user_model()
        user = User.objects.create_user(**validated_data)
        PatientProfile.objects.create(user=user, phone=phone)
        return user


class LoginSerializer(serializers.Serializer):
    username = serializers.CharField()
    password = serializers.CharField(write_only=True)
