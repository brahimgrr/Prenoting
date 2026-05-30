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
}
