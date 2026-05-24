<?php

namespace Tests\Feature;

use App\Support\ComuniItalianiCatalog;
use Tests\TestCase;

class ComuniItalianiCatalogTest extends TestCase
{
  public function test_loads_comuni_from_json_catalog(): void
  {
    $names = ComuniItalianiCatalog::names();

    $this->assertContains('ROMA', $names);
    $this->assertContains('FRANCIA', $names);
    $this->assertSame('H501', ComuniItalianiCatalog::codeFor('ROMA'));
  }
}
