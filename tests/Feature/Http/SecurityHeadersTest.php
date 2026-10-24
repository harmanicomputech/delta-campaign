<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Str;
use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function test_html_responses_carry_security_headers_and_a_nonce_based_csp(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);

        preg_match("/'nonce-([^']+)'/", $csp, $match);
        $this->assertNotEmpty($match[1] ?? null);
        $this->assertStringContainsString('nonce="'.$match[1].'"', $response->getContent(), 'Inline scripts must carry the CSP nonce.');
    }

    public function test_csp_nonce_changes_per_request(): void
    {
        $first = $this->get('/')->headers->get('Content-Security-Policy');
        $second = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($first, $second);
    }

    public function test_json_responses_have_no_csp_but_keep_baseline_headers(): void
    {
        $this->getJson('/health')
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_is_sent_only_when_enabled_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        config(['campaign.security.hsts' => true]);
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_every_response_gets_a_request_id(): void
    {
        $id = $this->get('/')->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid((string) $id));
    }

    public function test_a_safe_upstream_request_id_is_reused(): void
    {
        $this->get('/', ['X-Request-Id' => 'lb-abc123-xyz'])->assertHeader('X-Request-Id', 'lb-abc123-xyz');
    }

    public function test_an_unsafe_upstream_request_id_is_replaced(): void
    {
        $id = $this->get('/', ['X-Request-Id' => '<script>alert(1)</script>'])->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid((string) $id));
    }
}
