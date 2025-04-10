<?php declare(strict_types=1);
namespace Imbo\Storage;

use Aws\Api\DateTimeResult;
use Aws\CommandInterface;
use Aws\History;
use Aws\Middleware;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use DateTime;
use GuzzleHttp\Psr7\Response;
use Imbo\Exception\StorageException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(S3::class)]
class S3Test extends TestCase
{
    private string $key    = 'key';
    private string $secret = 'secret';
    private string $bucket = 'bucket';
    private string $region = 'eu-west-1';
    private History $history;

    private function getAdapter(MockHandler $handler): S3
    {
        $adapter = new S3($this->key, $this->secret, $this->bucket, $this->region, ['handler' => $handler]);

        $this->history = new History();
        $adapter->getClient()->getHandlerList()->appendSign(Middleware::history($this->history));

        return $adapter;
    }

    public function testCanStoreImages(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result());

        $this->assertTrue(
            $this->getAdapter($handler)->store('user', 'image-id', 'image data'),
            'Expected adapter to store image',
        );

        $this->assertCount(1, $this->history, 'Expected one result');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucket, $command['Bucket']);
        $this->assertSame('u/s/e/user/i/m/a/image-id', $command['Key']);
        $this->assertSame('image data', $command['Body']);
    }

    public function testThrowsExceptionWhenStoringImageFails(): void
    {
        $handler = new MockHandler();
        $handler->append(fn (CommandInterface $cmd) => new S3Exception('some error', $cmd));

        $this->expectExceptionObject(new StorageException('Unable to store image', 500));
        $this->getAdapter($handler)->store('user', 'image-id', 'image data');
    }

    public function testCanDeleteImage(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result(), new Result());

        $this->assertTrue(
            $this->getAdapter($handler)->delete('user', 'image-id'),
            'Expected adapter to delete image',
        );

        $this->assertCount(2, $this->history, 'Expected two results');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucket, $command['Bucket']);
        $this->assertSame('u/s/e/user/i/m/a/image-id', $command['Key']);
    }

    public function testThrowsExceptionWhenDeletingImageFails(): void
    {
        $handler = new MockHandler();
        $handler->append(
            new Result(),
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd),
        );

        $this->expectExceptionObject(new StorageException('Unable to delete image', 500));
        $this->getAdapter($handler)->delete('user', 'image-id');
    }

    public function testThrowsExceptionWhenDeletingImageThatDoesNotExist(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd): S3Exception => new S3Exception(
                'some error',
                $cmd,
                ['response' => new Response(404)],
            ),
        );

        $this->expectExceptionObject(new StorageException('File not found', 404));
        $this->getAdapter($handler)->delete('user', 'image-id');
    }

    public function testCanGetImage(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result(['Body' => 'image data']));

        $this->assertSame(
            'image data',
            $this->getAdapter($handler)->getImage('user', 'image-id'),
        );

        $this->assertCount(1, $this->history, 'Expected one result');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucket, $command['Bucket']);
        $this->assertSame('u/s/e/user/i/m/a/image-id', $command['Key']);
    }

    public function testThrowsExceptionWhenGettingImageThatDoesNotExist(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd, ['response' => new Response(404)]),
        );

        $this->expectExceptionObject(new StorageException('File not found', 404));
        $this->getAdapter($handler)->getImage('user', 'image-id');
    }

    public function testThrowsExceptionWhenGettingImageFails(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd),
        );

        $this->expectExceptionObject(new StorageException('Unable to get image', 500));
        $this->getAdapter($handler)->getImage('user', 'image-id');
    }

    public function testCanGetImageLastModified(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result(['LastModified' => DateTimeResult::fromEpoch(1594895257)]));

        $this->assertEquals(
            new DateTime('@1594895257'),
            $this->getAdapter($handler)->getLastModified('user', 'image-id'),
        );

        $this->assertCount(1, $this->history, 'Expected one result');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucket, $command['Bucket']);
        $this->assertSame('u/s/e/user/i/m/a/image-id', $command['Key']);
    }

    public function testThrowsExceptionWhenGettingLastModifiedOfImageThatDoesNotExist(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd, ['response' => new Response(404)]),
        );

        $this->expectExceptionObject(new StorageException('File not found', 404));
        $this->getAdapter($handler)->getLastModified('user', 'image-id');
    }

    public function testThrowsExceptionWhenGettingLastModifiedFails(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd),
        );

        $this->expectExceptionObject(new StorageException('Unable to get image', 500));
        $this->getAdapter($handler)->getLastModified('user', 'image-id');
    }

    public function testCanGetStatus(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result());

        $this->assertTrue(
            $this->getAdapter($handler)->getStatus(),
            'Expected status to be false',
        );
    }

    public function testStatusReturnsFalseOnError(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd),
        );

        $this->assertFalse(
            $this->getAdapter($handler)->getStatus(),
            'Expected status to be false',
        );
    }

    public function testCanCanCheckIfImageExists(): void
    {
        $handler = new MockHandler();
        $handler->append(new Result());

        $this->assertTrue(
            $this->getAdapter($handler)->imageExists('user', 'image-id'),
            'Expected image to exist',
        );

        $this->assertCount(1, $this->history, 'Expected one result');
        $command = $this->history->getLastCommand()->toArray();
        $this->assertSame($this->bucket, $command['Bucket']);
        $this->assertSame('u/s/e/user/i/m/a/image-id', $command['Key']);
    }

    public function testCheckForImageExistReturnsFalseOnFailure(): void
    {
        $handler = new MockHandler();
        $handler->append(
            fn (CommandInterface $cmd) => new S3Exception('some error', $cmd),
        );

        $this->assertFalse(
            $this->getAdapter($handler)->imageExists('user', 'image-id'),
            'Did not expect image to exist',
        );
    }
}
