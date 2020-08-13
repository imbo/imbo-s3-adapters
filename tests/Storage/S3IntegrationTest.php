<?php declare(strict_types=1);
namespace Imbo\Storage;

use Aws\S3\S3Client;
use DateTime;
use DateTimeZone;

/**
 * @coversDefaultClass Imbo\Storage\S3
 * @group integration
 */
class S3IntegrationTest extends StorageTests {
    private function checkEnv() : void {
        $required = [
            'S3_KEY',
            'S3_SECRET',
            'S3_BUCKET',
            'S3_REGION',
        ];
        $missing = [];

        foreach ($required as $var) {
            if (empty(getenv($var))) {
                $missing[] = $var;
            }
        }

        if (count($missing)) {
            $this->markTestSkipped(sprintf('Missing required environment variable(s) for the integration tests: %s', join(', ', $missing)));
        }
    }

    protected function getAdapter() : S3 {
        $this->checkEnv();

        return new S3(
            (string) getenv('S3_KEY'),
            (string) getenv('S3_SECRET'),
            (string) getenv('S3_BUCKET'),
            (string) getenv('S3_REGION'),
        );
    }

    public function setUp() : void {
        parent::setUp();

        $this->checkEnv();

        $client = new S3Client([
            'region'      => (string) getenv('S3_REGION'),
            'version'     => 'latest',
            'credentials' => [
                'key'    => (string) getenv('S3_KEY'),
                'secret' => (string) getenv('S3_SECRET'),
            ],
        ]);

        /** @var array{Contents: array<int, array{Key: string}>} */
        $objects      = $client->listObjects(['Bucket' => (string) getenv('S3_BUCKET')])->toArray();
        $keysToDelete = array_map(fn(array $object) : array => ['Key' => $object['Key']], $objects['Contents'] ?? []);

        if (!empty($keysToDelete)) {
            $client->deleteObjects([
                'Bucket' => (string) getenv('S3_BUCKET'),
                'Delete' => ['Objects' => $keysToDelete],
            ]);
        }
    }
}
