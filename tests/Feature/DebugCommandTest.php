<?php

namespace Imran\DynamicConfig\Tests\Feature;

use Imran\DynamicConfig\Tests\TestCase;

class DebugCommandTest extends TestCase
{
    protected function setUpSensitiveFixture(): void
    {
        file_put_contents(__DIR__.'/../fixtures/app.json', json_encode([
            'services' => ['stripe' => ['secret' => 'sk_live_super_secret']],
        ]));

        config()->set('dynamic-config.sources', [
            [
                'driver' => 'json',
                'priority' => 10,
                'path' => __DIR__.'/../fixtures/app.json',
            ],
        ]);

        // protect_sensitive_keys (on by default) would otherwise strip this
        // value before it even reaches the resolved tree - these tests are
        // about the *display* redaction layer specifically, so allow the
        // value through as if it were a deliberate, reviewed override.
        config()->set('dynamic-config.sensitive_key_exceptions', ['services.stripe.secret']);
    }

    public function test_sensitive_values_are_redacted_by_default()
    {
        $this->setUpSensitiveFixture();

        $this->artisan('dynamic-config:debug', ['key' => 'services.stripe.secret'])
            ->expectsOutputToContain('redacted')
            ->assertExitCode(0);
    }

    public function test_reveal_option_prints_the_real_value()
    {
        $this->setUpSensitiveFixture();

        $this->artisan('dynamic-config:debug', ['key' => 'services.stripe.secret', '--reveal' => true])
            ->expectsOutputToContain('sk_live_super_secret')
            ->assertExitCode(0);
    }

    public function test_nested_sensitive_keys_are_redacted_when_a_parent_key_is_requested()
    {
        $this->setUpSensitiveFixture();

        // Asking for the parent ("services.stripe") must not be a way to
        // bypass redaction of the sensitive child ("secret") inside it.
        $this->artisan('dynamic-config:debug', ['key' => 'services.stripe'])
            ->expectsOutputToContain('redacted')
            ->doesntExpectOutputToContain('sk_live_super_secret')
            ->assertExitCode(0);
    }

    public function test_non_sensitive_values_are_shown_normally()
    {
        $this->artisan('dynamic-config:debug', ['key' => 'app.name'])
            ->expectsOutputToContain('Yaml App')
            ->assertExitCode(0);
    }
}
