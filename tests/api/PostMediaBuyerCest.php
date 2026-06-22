<?php

namespace Tests\api;

use Codeception\Example;
use Tests\ApiTester;
use Tests\_support\Factory\MediaBuyerFactory;

/**
 * Tests for POST /api/mediabuyers
 *
 * Covers acceptance criteria P1–P11 from the API contract.
 */
class PostMediaBuyerCest
{
    private string $endpoint = '/mediabuyers';
    private string $schemaPath;
    private MediaBuyerFactory $factory;

    public function _before(ApiTester $I): void
    {
        $this->factory    = new MediaBuyerFactory();
        $this->schemaPath = codecept_root_dir('tests/schemas/post-media-buyer-schema.json');

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    /**
     * P1 — A valid request returns HTTP 200, Content-Type: application/json,
     *       and a body conforming to post-media-buyer-schema.json.
     */
    public function testValidRequestReturns200AndConformsToSchema(ApiTester $I): void
    {
        $I->sendPost($this->endpoint, $this->factory->makeJson(['mbId' => '9001']));

        $I->seeResponseCodeIs(200);
        $I->assertStringContainsString('application/json', $I->grabHttpHeader('Content-Type'));
        $I->seeResponseMatchesJsonSchema($this->schemaPath);
    }

    /**
     * P2 — data.id is a server-generated positive integer.
     *       The request payload must never contain id.
     */
    public function testServerGeneratesPositiveIntegerId(ApiTester $I): void
    {
        $payload = $this->factory->make(['mbId' => '9002']);
        $I->assertArrayNotHasKey('id', $payload, 'Factory must not include id in request');

        $I->sendPost($this->endpoint, json_encode($payload));

        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);

        $I->assertIsInt($response['data']['id'], 'data.id must be an integer');
        $I->assertGreaterThan(0, $response['data']['id'], 'data.id must be a positive integer');
    }

    /**
     * P3 — The response echoes back the same mbId, initials, name,
     *       email, and slackUserId that were sent in the request.
     */
    public function testResponseEchoesRequestFields(ApiTester $I): void
    {
        $payload = $this->factory->make(['mbId' => '9003']);
        $I->sendPost($this->endpoint, json_encode($payload));

        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true)['data'];

        foreach (['mbId', 'initials', 'name', 'email', 'slackUserId'] as $field) {
            $I->assertEquals(
                $payload[$field],
                $response[$field],
                "Field '{$field}' in response does not match request"
            );
        }
    }

    /**
     * P4a — active: true in the request must result in data.active === 1.
     */
    public function testActiveTrueResultsInIntegerOne(ApiTester $I): void
    {
        $I->sendPost($this->endpoint, $this->factory->makeJson(['mbId' => '9004', 'active' => true]));

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['active' => 1]]);
    }

    /**
     * P4b — active: false in the request must result in data.active === 0.
     */
    public function testActiveFalseResultsInIntegerZero(ApiTester $I): void
    {
        $I->sendPost($this->endpoint, $this->factory->makeJson(['mbId' => '9005', 'active' => false]));

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['active' => 0]]);
    }

    // -------------------------------------------------------------------------
    // Validation — missing required fields (P5, parameterized)
    // -------------------------------------------------------------------------

    /**
     * P5 — Omitting any required field returns HTTP 400 with an errors array
     *       that names the missing field.
     *
     * @dataProvider missingRequiredFieldsProvider
     */
    public function testMissingRequiredFieldReturns400(ApiTester $I, Example $example): void
    {
        $field   = $example['field'];
        $payload = $this->factory->without($field);

        $I->sendPost($this->endpoint, json_encode($payload));

        $I->seeResponseCodeIs(400);
        $I->seeResponseJsonMatchesJsonPath('$.errors');
        $I->seeResponseContains($field);
    }

    protected function missingRequiredFieldsProvider(): array
    {
        return [
            ['field' => 'mbId'],
            ['field' => 'name'],
            ['field' => 'email'],
            ['field' => 'active'],
        ];
    }

    // -------------------------------------------------------------------------
    // Validation — field rules
    // -------------------------------------------------------------------------

    /**
     * P6 — An invalid email returns HTTP 400 and the error message
     *       references the invalid value.
     */
    public function testInvalidEmailReturns400(ApiTester $I): void
    {
        $I->sendPost($this->endpoint, $this->factory->makeJson(['email' => 'not-an-email']));

        $I->seeResponseCodeIs(400);
        $I->seeResponseContains('not-an-email');
    }

    /**
     * P7 — initials longer than 2 characters returns HTTP 400 with the
     *       exact message: "The initials must be exactly 2 characters long."
     */
    public function testInitialsTooLongReturns400WithExactMessage(ApiTester $I): void
    {
        $I->sendPost($this->endpoint, $this->factory->makeJson(['initials' => 'TOO']));

        $I->seeResponseCodeIs(400);
        $I->seeResponseContains('The initials must be exactly 2 characters long.');
    }

    /**
     * P8 — name boundary: too short (< 2 chars) returns HTTP 400.
     *
     * @dataProvider nameBoundaryProvider
     */
    public function testNameOutsideBoundaryReturns400(ApiTester $I, Example $example): void
    {
        $I->sendPost($this->endpoint, $this->factory->makeJson(['name' => $example['name']]));

        $I->seeResponseCodeIs(400);
        $I->seeResponseJsonMatchesJsonPath('$.errors');
    }

    protected function nameBoundaryProvider(): array
    {
        return [
            ['name' => 'A',                   'case' => '1 char — below minimum'],
            ['name' => str_repeat('A', 31),   'case' => '31 chars — above maximum'],
        ];
    }

    /**
     * P9 — mbId that is not a numeric string (e.g. "abc") returns HTTP 400.
     */
    public function testNonNumericMbIdReturns400(ApiTester $I): void
    {
        $I->sendPost($this->endpoint, $this->factory->makeJson(['mbId' => 'abc']));

        $I->seeResponseCodeIs(400);
        $I->seeResponseJsonMatchesJsonPath('$.errors');
    }

    /**
     * P10 — active sent as a string ("yes") instead of a boolean returns HTTP 400.
     *
     * The payload is built as a raw JSON string to prevent PHP from coercing
     * the value to a native bool before encoding.
     */
    public function testNonBooleanActiveReturns400(ApiTester $I): void
    {
        $raw = '{"mbId":"9001","initials":"TM","name":"Test Media","email":"t@example.com","slackUserId":"U123","active":"yes"}';

        $I->sendPost($this->endpoint, $raw);

        $I->seeResponseCodeIs(400);
        $I->seeResponseJsonMatchesJsonPath('$.errors');
    }

    /**
     * P11 — Creating two media buyers with the same mbId returns an error
     *        on the second request.
     *
     * Assumption: the server returns either 400 or 409 — either is acceptable.
     * See README for full rationale.
     */
    public function testDuplicateMbIdReturnsError(ApiTester $I): void
    {
        $payload = $this->factory->makeJson(['mbId' => '77777']);

        $I->sendPost($this->endpoint, $payload);
        $I->seeResponseCodeIs(200);

        $I->sendPost($this->endpoint, $payload);
        $statusCode = $I->grabResponseCode();

        $I->assertTrue(
            in_array($statusCode, [400, 409], true),
            "Expected 400 or 409 for duplicate mbId, got {$statusCode}"
        );
        $I->seeResponseJsonMatchesJsonPath('$.errors');
    }
}
