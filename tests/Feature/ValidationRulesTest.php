<?php

namespace Tests\Feature;

use App\Support\ValidationRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationRulesTest extends TestCase
{
  public function test_email_requires_top_level_domain(): void
  {
    $validator = Validator::make([
      'email' => 'pippo@gmail',
    ], [
      'email' => ValidationRules::email(),
    ]);

    $this->assertTrue($validator->fails());
  }

  public function test_email_accepts_complete_domain(): void
  {
    $validator = Validator::make([
      'email' => 'pippo@gmail.com',
    ], [
      'email' => ValidationRules::email(),
    ]);

    $this->assertFalse($validator->fails());
  }

  public function test_phone_rejects_repeated_separators(): void
  {
    $validator = Validator::make([
      'phone' => '555-----------0100',
    ], [
      'phone' => ValidationRules::phone(),
    ]);

    $this->assertTrue($validator->fails());
  }

  public function test_phone_accepts_italian_mobile_number(): void
  {
    $validator = Validator::make([
      'phone' => '3331234567',
    ], [
      'phone' => ValidationRules::phone(),
    ]);

    $this->assertFalse($validator->fails());
  }

  public function test_phone_accepts_italian_mobile_number_with_prefix(): void
  {
    foreach (['+393331234567', '+39 3213211234', '00393331234567'] as $phone) {
      $validator = Validator::make([
        'phone' => $phone,
      ], [
        'phone' => ValidationRules::phone(),
      ]);

      $this->assertFalse($validator->fails(), "{$phone} should be valid.");
    }
  }
}
