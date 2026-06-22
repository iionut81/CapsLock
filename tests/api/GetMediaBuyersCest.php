<?php

namespace Tests\api;

use Tests\ApiTester;

/**
 * Tests for GET /api/mediabuyers
 *
 * Covers acceptance criteria G1–G7 from the API contract.
 */
class GetMediaBuyersCest
{
    private string $endpoint = '/mediabuyers';
    private string $schemaPath;

    public function _before(ApiTester $I): void
    {
        $I->haveHttpHeader('Accept', 'application/json');
        $this->schemaPath = codecept_root_dir('tests/schemas/get-media-buyers-schema.json');
    }

    /**
     * G1 — Returns HTTP 200 with Content-Type: application/json.
     */
    public function testReturns200WithJsonContentType(ApiTester $I): void
    {
        $I->sendGet($this->endpoint);

        $I->seeResponseCodeIs(200);
        $I->assertStringContainsString('application/json', $I->grabHttpHeader('Content-Type'));
    }

    /**
     * G2 — Response body conforms to get-media-buyers-schema.json.
     */
    public function testResponseConformsToSchema(ApiTester $I): void
    {
        $I->sendGet($this->endpoint);

        $I->seeResponseCodeIs(200);
        $I->seeResponseMatchesJsonSchema($this->schemaPath);
    }

    /**
     * G3a — data is always an array (non-empty collection case).
     *
     * Confirms the field is an array regardless of how many records exist.
     * The empty-collection variant (G3b) requires state control and is skipped below.
     */
    public function testDataIsArray(ApiTester $I): void
    {
        $I->sendGet($this->endpoint);

        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);

        $I->assertIsArray($response['data'] ?? null, '"data" must be an array, not null or missing');
    }

    /**
     * G3b — When zero buyers exist, returns {"data": []} rather than 404 or null.
     *
     * Skipped: requires a seed-reset endpoint to guarantee an empty collection
     * before the GET. Enable once state-control infrastructure exists.
     */
    public function testEmptyCollectionReturnsEmptyDataArray(ApiTester $I): void
    {
        $I->markTestSkipped('Requires a seed-reset endpoint; enable once state control exists.');

        // Prerequisite: call reset/truncate endpoint here to empty the collection.
        $I->sendGet($this->endpoint);
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);
        $I->assertIsArray($response['data'], '"data" must be an array');
        $I->assertCount(0, $response['data'], '"data" must be [] when no buyers exist, not 404 or null');
    }

    /**
     * G4 — Every item in data contains all required fields.
     */
    public function testEveryItemContainsAllRequiredFields(ApiTester $I): void
    {
        $I->sendGet($this->endpoint);

        $I->seeResponseCodeIs(200);
        $response       = json_decode($I->grabResponse(), true);
        $requiredFields = ['id', 'mbId', 'initials', 'name', 'email', 'slackUserId', 'active'];

        foreach ($response['data'] as $index => $buyer) {
            foreach ($requiredFields as $field) {
                $I->assertArrayHasKey(
                    $field,
                    $buyer,
                    "Item [{$index}] is missing required field '{$field}'"
                );
            }
        }
    }

    /**
     * G5 — email values are syntactically valid RFC email addresses.
     */
    public function testEmailValuesAreSyntacticallyValid(ApiTester $I): void
    {
        $I->sendGet($this->endpoint);

        $response = json_decode($I->grabResponse(), true);

        foreach ($response['data'] as $index => $buyer) {
            $I->assertNotFalse(
                filter_var($buyer['email'], FILTER_VALIDATE_EMAIL),
                "Item [{$index}] has an invalid email address: '{$buyer['email']}'"
            );
        }
    }

    /**
     * G6 — active is always 0 or 1 as an integer — not a boolean, not a string.
     */
    public function testActiveIsIntegerZeroOrOne(ApiTester $I): void
    {
        $I->sendGet($this->endpoint);

        $response = json_decode($I->grabResponse(), true);

        foreach ($response['data'] as $index => $buyer) {
            $I->assertIsInt(
                $buyer['active'],
                "Item [{$index}] active must be integer, got " . gettype($buyer['active'])
            );
            $I->assertContains(
                $buyer['active'],
                [0, 1],
                "Item [{$index}] active must be 0 or 1, got {$buyer['active']}"
            );
        }
    }

    /**
     * G7 — id values are unique across all items in the response.
     */
    public function testIdValuesAreUnique(ApiTester $I): void
    {
        $I->sendGet($this->endpoint);

        $response = json_decode($I->grabResponse(), true);
        $ids      = array_column($response['data'], 'id');

        $I->assertCount(
            count(array_unique($ids)),
            $ids,
            'Duplicate id values found in response: ' . implode(', ', array_diff_assoc($ids, array_unique($ids)))
        );
    }
}
