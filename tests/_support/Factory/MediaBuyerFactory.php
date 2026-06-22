<?php

namespace Tests\_support\Factory;

/**
 * Builder for MediaBuyer request payloads.
 *
 * All test methods receive payloads from this factory — no hard-coded JSON
 * inside test classes. Overrides are merged per-test so that each test only
 * declares the fields relevant to its scenario.
 *
 * Usage:
 *   $factory = new MediaBuyerFactory();
 *   $payload = $factory->make();                        // valid defaults
 *   $payload = $factory->make(['active' => false]);     // override one field
 *   $payload = $factory->without('email');              // omit a required field
 *   $payload = $factory->without('name', 'active');     // omit multiple fields
 */
class MediaBuyerFactory
{
    private array $defaults = [
        'mbId'        => '9001',
        'initials'    => 'TM',
        'name'        => 'Test Media Buyer',
        'email'       => 'test.media.buyer@example.com',
        'slackUserId' => 'U05AZ3DQBBKK',
        'active'      => true,
    ];

    /**
     * Returns a valid payload merged with the given overrides.
     */
    public function make(array $overrides = []): array
    {
        return array_merge($this->defaults, $overrides);
    }

    /**
     * Returns a valid payload with the specified fields removed.
     * Useful for testing missing-required-field validations (P5).
     */
    public function without(string ...$fields): array
    {
        $payload = $this->defaults;
        foreach ($fields as $field) {
            unset($payload[$field]);
        }
        return $payload;
    }

    /**
     * Returns the default payload as raw JSON string.
     * Useful when the test needs to inject a non-PHP-native type (e.g. active: "yes")
     * that PHP's json_encode would coerce — build the string manually in that case.
     */
    public function makeJson(array $overrides = []): string
    {
        return json_encode($this->make($overrides), JSON_THROW_ON_ERROR);
    }
}
