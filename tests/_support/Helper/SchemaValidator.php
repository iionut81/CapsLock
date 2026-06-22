<?php

namespace Tests\_support\Helper;

use Codeception\Module;
use JsonSchema\Validator;

/**
 * Codeception helper that validates the last REST response against a JSON Schema file.
 *
 * Uses justinrainbow/json-schema (draft-07). Add to a suite via:
 *   modules:
 *     enabled:
 *       - \Tests\_support\Helper\SchemaValidator
 */
class SchemaValidator extends Module
{
    /**
     * Asserts that the current REST response body conforms to the given JSON Schema file.
     *
     * @param string $schemaPath Absolute path to the .json schema file.
     */
    public function seeResponseMatchesJsonSchema(string $schemaPath): void
    {
        /** @var \Codeception\Module\REST $rest */
        $rest        = $this->getModule('REST');
        $body        = $rest->grabResponse();
        $decoded     = json_decode($body);

        $this->assertNotNull($decoded, 'Response body is not valid JSON');

        $schema    = (object)['$ref' => 'file://' . str_replace('\\', '/', realpath($schemaPath))];
        $validator = new Validator();
        $validator->validate($decoded, $schema);

        $this->assertTrue(
            $validator->isValid(),
            sprintf(
                "Response does not conform to schema '%s'.\nErrors:\n%s\n\nResponse:\n%s",
                basename($schemaPath),
                json_encode($validator->getErrors(), JSON_PRETTY_PRINT),
                json_encode($decoded, JSON_PRETTY_PRINT)
            )
        );
    }
}
