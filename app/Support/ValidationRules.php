<?php

namespace App\Support;

class ValidationRules
{
  public const PHONE_PATTERN = '/\A(?=(?:\D*\d){6,})\+?[0-9\s().-]+\z/';

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
