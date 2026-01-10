<?php declare(strict_types=1);

namespace Imbo\Storage;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use ImboSDK\Storage\StorageTests;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

use function count;
use function sprintf;

#[Group('integration')]
#[CoversClass(S3::class)]
class S3IntegrationTest extends StorageTests
{
    protected int $allowedTimestampDelta = 5;

    private function checkEnv(): void
    {
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
            $this->markTestSkipped(sprintf('Missing required environment variable(s) for the integration tests: %s', implode(', ', $missing)));
        }
    }

    protected function getAdapter(): S3
    {
        $this->checkEnv();

        return new S3(
            (string) getenv('S3_BUCKET'),
            (string) getenv('S3_KEY'),
            (string) getenv('S3_SECRET'),
            (string) getenv('S3_REGION'),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkEnv();

        $client = new S3Client([
            'region' => (string) getenv('S3_REGION'),
            'version' => 'latest',
            'credentials' => new Credentials((string) getenv('S3_KEY'), (string) getenv('S3_SECRET')),
        ]);

        $objects = $client->listObjects(['Bucket' => (string) getenv('S3_BUCKET')])->toArray();
        $keysToDelete = array_map(
            fn (array $object): array => ['Key' => $object['Key']],
            $objects['Contents'] ?? [],
        );

        if (!empty($keysToDelete)) {
            $client->deleteObjects([
                'Bucket' => (string) getenv('S3_BUCKET'),
                'Delete' => ['Objects' => $keysToDelete],
            ]);
        }
    }
}
