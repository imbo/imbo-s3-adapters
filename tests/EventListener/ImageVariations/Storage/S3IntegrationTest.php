<?php declare(strict_types=1);
namespace Imbo\EventListener\ImageVariations\Storage;

use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Imbo\EventListener\ImageVariations\Storage\S3
 * @group integration
 */
class S3IntegrationTest extends TestCase
{
    private S3 $adapter;
    private string $user    = 'user';
    private string $imageId = 'image-id';
    private string $fixturesDir;

    public function setUp(): void
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
            $this->markTestSkipped(sprintf('Missing required environment variable(s) for the integration tests: %s', join(', ', $missing)));
        }

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
        $keysToDelete = array_map(fn (array $object): array => ['Key' => $object['Key']], $objects['Contents'] ?? []);

        if (!empty($keysToDelete)) {
            $client->deleteObjects([
                'Bucket' => (string) getenv('S3_BUCKET'),
                'Delete' => ['Objects' => $keysToDelete],
            ]);
        }

        $this->adapter = new S3(
            (string) getenv('S3_KEY'),
            (string) getenv('S3_SECRET'),
            (string) getenv('S3_BUCKET'),
            (string) getenv('S3_REGION'),
        );

        $this->fixturesDir = __DIR__ . '/../../../fixtures';
    }

    /**
     * @covers ::storeImageVariation
     * @covers ::getImageVariation
     * @covers ::deleteImageVariations
     */
    public function testCanIntegrateWithS3(): void
    {
        foreach ([100, 200, 300] as $width) {
            $this->assertTrue(
                $this->adapter->storeImageVariation($this->user, $this->imageId, (string) file_get_contents($this->fixturesDir . '/test-image.png'), $width),
                sprintf('Expected adapter to store image with width %d', $width),
            );
        }

        foreach ([100, 200, 300] as $width) {
            $this->assertSame(
                (string) file_get_contents($this->fixturesDir . '/test-image.png'),
                $this->adapter->getImageVariation($this->user, $this->imageId, $width),
                'Expected images to match'
            );
        }

        $this->assertTrue(
            $this->adapter->deleteImageVariations($this->user, $this->imageId, 100),
            'Expected image variation with width 100 to be deleted',
        );

        $this->assertTrue(
            $this->adapter->deleteImageVariations($this->user, $this->imageId),
            'Expected the rest of the image variations to be deleted',
        );
    }
}
