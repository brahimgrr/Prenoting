<?php

namespace App\Support;

class ValidationRules
{
  public const PHONE_PATTERN = '/\A(?=(?:\D*\d){6,})\+?[0-9\s().-]+\z/';
  public const EMAIL_WITH_TOP_LEVEL_DOMAIN_PATTERN = '/\A[^@\s]+@[^@\s]+\.[^@\s]+\z/';

  public static function email(bool $required = true, int $max = 255): array
  {
    return [
      $required ? 'required' : 'nullable',
      'email',
      'max:'.$max,
      'regex:'.self::EMAIL_WITH_TOP_LEVEL_DOMAIN_PATTERN,
    ];
  }

  public static function phone(bool $required = true): array
  {
    return [
      $required ? 'required' : 'nullable',
      'string',
      'max:32',
      'regex:'.self::PHONE_PATTERN,
    ];
  }
}
