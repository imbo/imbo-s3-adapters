<?php declare(strict_types=1);
namespace Imbo\EventListener\ImageVariations\Storage;

use Aws\CommandInterface;
use Aws\History;
use Aws\Middleware;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Imbo\Exception\StorageException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(S3::class)]
class S3Test extends TestCase
{
    private string $bucketName = 'bucket';
    private History $history;

    private function getAdapter(MockHandler $handler): S3
    {
        $this->history = new History();
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'eu-west-1',
            'credentials' => [
                'key' => 'key',
                'secret' => 'secret',
            ],
            'handler' => $handler,
        ]);
        $client
            ->getHandlerList()
            ->appendSign(Middleware::history($this->history));

        return new S3($this->bucketName, client: $client);
    }

    public function testCanStoreImageVariations(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result());
        $this->getAdapter($handler)->storeImageVariation('user', 'image-id', 'image data', 100);
        $this->assertCount(1, $this->history, 'Expected one result');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucketName, $command['Bucket']);
        $this->assertSame('imageVariation/u/s/e/user/i/m/a/image-id/100', $command['Key']);
        $this->assertSame('image data', $command['Body']);
    }

    public function testThrowsExceptionWhenStoringImageVariationFails(): void
    {
        $handler = new MockHandler();
        $handler->append(fn (CommandInterface $cmd) => new S3Exception('some error', $cmd));

        $this->expectExceptionObject(new StorageException('Unable to store image', 500));
        $this->getAdapter($handler)->storeImageVariation('user', 'image-id', 'image data', 100);
    }

    public function testCanDeleteImageVariations(): void
    {
        $handler = new MockHandler();
        $handler->append(
            new Result([
                'Contents' => [
                    ['Key' => 'key1', 'Size' => 123],
                    ['Key' => 'key2', 'Size' => 456],
                    ['Key' => 'key3', 'Size' => 789],
                ],
            ]),
            new Result(),
        );

        $this->getAdapter($handler)->deleteImageVariations('user', 'image-id');
        $this->assertCount(2, $this->history, 'Expected two results');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucketName, $command['Bucket']);
        $this->assertSame(['Objects' => [['Key' => 'key1'], ['Key' => 'key2'], ['Key' => 'key3']]], $command['Delete']);
    }

    public function testCanDeleteSpecificImageVariation(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result());
        $this->getAdapter($handler)->deleteImageVariations('user', 'image-id', 100);
        $this->assertCount(1, $this->history, 'Expected one result');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucketName, $command['Bucket']);
        $this->assertSame('imageVariation/u/s/e/user/i/m/a/image-id/100', $command['Key']);
    }

    public function testThrowsExceptionWhenDeletingSpecificImageVariationFails(): void
    {
        $handler = new MockHandler();
        $handler->append(fn (CommandInterface $cmd) => new S3Exception('some error', $cmd));

        $this->expectExceptionObject(new StorageException('Unable to delete image variation', 500));
        $this->getAdapter($handler)->deleteImageVariations('user', 'image-id', 100);
    }

    public function testThrowsExceptionWhenResultHasNoBody(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result());

        $this->expectExceptionObject(new StorageException('Unable to get image variation', 500));
        $this->getAdapter($handler)->getImageVariation('user', 'image-id', 100);
    }

    public function testThrowsExceptionWhenDeletingImageVariationsFails(): void
    {
        $handler = new MockHandler();
        $handler->append(
            new Result(['Contents' => [['Key' => 'some-key']]]),
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd),
        );

        $this->expectExceptionObject(new StorageException('Unable to delete image variations', 500));
        $this->getAdapter($handler)->deleteImageVariations('user', 'image-id');
    }

    public function testCanGetImageVariation(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result(['Body' => Utils::streamFor('image data')]));

        $this->assertSame(
            'image data',
            $this->getAdapter($handler)->getImageVariation('user', 'image-id', 100),
        );

        $this->assertCount(1, $this->history, 'Expected one result');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucketName, $command['Bucket']);
        $this->assertSame('imageVariation/u/s/e/user/i/m/a/image-id/100', $command['Key']);
    }

    public function testGetImageVariationFailsWhenImageDoesNotExist(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd, ['response' => new Response(404)]),
        );

        $this->expectExceptionObject(new StorageException('File not found', 404));
        $this->getAdapter($handler)->getImageVariation('user', 'image-id', 100);
    }

    public function testGetImageVariationFailsWhenCommandFails(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd),
        );

        $this->expectExceptionObject(new StorageException('Unable to get image variation', 500));
        $this->getAdapter($handler)->getImageVariation('user', 'image-id', 100);
    }
}
