<?php declare(strict_types=1);

namespace Imbo\EventListener\ImageVariations\Storage;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use ImboSDK\EventListener\ImageVariations\Storage\StorageTests;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresEnvironmentVariable;

#[Group('integration')]
#[CoversClass(S3::class)]
#[RequiresEnvironmentVariable('S3_BUCKET')]
#[RequiresEnvironmentVariable('S3_KEY')]
#[RequiresEnvironmentVariable('S3_SECRET')]
#[RequiresEnvironmentVariable('S3_REGION')]
class S3IntegrationTest extends StorageTests
{
    protected function getAdapter(): S3
    {
        $bucket = (string) getenv('S3_BUCKET');
        $key = (string) getenv('S3_KEY');
        $secret = (string) getenv('S3_SECRET');
        $region = (string) getenv('S3_REGION');

        $client = new S3Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => new Credentials($key, $secret),
        ]);

        $objects = $client->listObjects(['Bucket' => $bucket])->toArray();
        $keysToDelete = array_map(
            static fn (array $object): array => ['Key' => $object['Key']],
            $objects['Contents'] ?? [],
        );

        if (!empty($keysToDelete)) {
            $client->deleteObjects([
                'Bucket' => $bucket,
                'Delete' => ['Objects' => $keysToDelete],
            ]);
        }

        return new S3($bucket, $key, $secret, $region);
    }
}
