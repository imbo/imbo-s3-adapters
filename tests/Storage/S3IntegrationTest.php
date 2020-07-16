<?php declare(strict_types=1);
namespace Imbo\Storage;

use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use DateTime;
use DateTimeZone;

/**
 * @coversDefaultClass Imbo\Storage\S3
 * @group integration
 */
class S3IntegrationTest extends TestCase {
    private S3 $adapter;
    private string $user    = 'user';
    private string $imageId = 'image-id';
    private string $fixturesDir;

    public function setUp() : void {
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
        $keysToDelete = array_map(fn(array $object) : array => ['Key' => $object['Key']], $objects['Contents'] ?? []);

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

        $this->fixturesDir = __DIR__ . '/../fixtures';
    }

    /**
     * @covers ::store
     * @covers ::delete
     * @covers ::getImage
     * @covers ::getLastModified
     * @covers ::getStatus
     * @covers ::imageExists
     */
    public function testCanIntegrateWithS3() : void {
        $this->assertTrue(
            $this->adapter->getStatus(),
            'Expected status to be true',
        );

        $this->assertFalse(
            $this->adapter->imageExists($this->user, $this->imageId),
            'Did not expect image to exist',
        );

        $this->assertTrue(
            $this->adapter->store($this->user, $this->imageId, (string) file_get_contents($this->fixturesDir . '/test-image.png')),
            'Expected adapter to store image',
        );

        $this->assertEqualsWithDelta(
            (new DateTime('now', new DateTimeZone('UTC')))->getTimestamp(),
            $this->adapter->getLastModified($this->user, $this->imageId)->getTimestamp(),
            5,
            'Expected timestamps to be equal',
        );

        $this->assertTrue(
            $this->adapter->imageExists($this->user, $this->imageId),
            'Expected image to exist',
        );

        $this->assertSame(
            (string) file_get_contents($this->fixturesDir . '/test-image.png'),
            $this->adapter->getImage($this->user, $this->imageId),
            'Expected images to match'
        );

        $this->assertTrue(
            $this->adapter->delete($this->user, $this->imageId),
            'Expected image to be deleted',
        );

        $this->assertFalse(
            $this->adapter->imageExists($this->user, $this->imageId),
            'Did not expect image to exist',
        );
    }
}
