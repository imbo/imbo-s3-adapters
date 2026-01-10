<?php declare(strict_types=1);

namespace Imbo\EventListener\ImageVariations\Storage;

use Aws\Credentials\Credentials;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
use Imbo\Exception\StorageException;
use InvalidArgumentException;

use const STR_PAD_LEFT;

class S3 implements StorageInterface
{
    private S3Client $client;

    /**
     * Create an S3 storage adapter.
     *
     * @param string       $bucketName   The name of the bucket
     * @param string       $accessKey    The access key for the bucket
     * @param string       $secret       The secret key for the bucket
     * @param string       $region       The region of the bucket
     * @param array<mixed> $clientParams Extra parameters for the S3 client constructor
     * @param ?S3Client    $client       Pre-configured S3 client. When specified none of the other parameters are used
     *
     * @throws StorageException
     */
    public function __construct(
        private string $bucketName,
        string $accessKey = '',
        string $secret = '',
        string $region = '',
        array $clientParams = [],
        ?S3Client $client = null,
    ) {
        try {
            $this->client = $client ?: new S3Client(array_replace_recursive(
                [
                    'version' => 'latest',
                    'region' => $region,
                    'credentials' => new Credentials($accessKey, $secret),
                ],
                $clientParams,
            ));
        } catch (InvalidArgumentException $e) {
            throw new StorageException('Unable to create S3 client', 500, $e);
        }
    }

    public function storeImageVariation(string $user, string $imageIdentifier, string $blob, int $width): void
    {
        $key = $this->getImagePath($user, $imageIdentifier, $width);

        try {
            $this->client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'Body' => $blob,
            ]);
        } catch (S3Exception $e) {
            throw new StorageException('Unable to store image variation', 500, $e);
        }

        return;
    }

    public function getImageVariation(string $user, string $imageIdentifier, int $width): string
    {
        $key = $this->getImagePath($user, $imageIdentifier, $width);

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
            ]);
        } catch (S3Exception $e) {
            if (404 === $e->getStatusCode()) {
                throw new StorageException('File not found', 404, $e);
            }

            throw new StorageException('Unable to get image variation', 500, $e);
        }

        /** @var ?Stream */
        $body = $result->get('Body');

        if (null === $body) {
            throw new StorageException('Unable to get image variation', 500);
        }

        return $body->getContents();
    }

    public function deleteImageVariations(string $user, string $imageIdentifier, ?int $width = null): void
    {
        $key = $this->getImagePath($user, $imageIdentifier, $width);

        if (null !== $width) {
            try {
                $this->client->deleteObject([
                    'Bucket' => $this->bucketName,
                    'Key' => $key,
                ]);
            } catch (S3Exception $e) {
                throw new StorageException('Unable to delete image variations', 500, $e);
            }

            return;
        }

        $objects = $this->client->listObjects([
            'Bucket' => $this->bucketName,
            'Prefix' => $key,
        ])->toArray();
        $keysToDelete = array_map(
            fn (array $object): array => ['Key' => $object['Key']],
            $objects['Contents'] ?? [],
        );

        if (empty($keysToDelete)) {
            return;
        }

        try {
            $this->client->deleteObjects([
                'Bucket' => $this->bucketName,
                'Delete' => ['Objects' => $keysToDelete],
            ]);
        } catch (S3Exception $e) {
            throw new StorageException('Unable to delete image variations', 500, $e);
        }
    }

    private function getImagePath(string $user, string $imageIdentifier, ?int $width = null): string
    {
        $userPath = str_pad($user, 3, '0', STR_PAD_LEFT);
        $parts = [
            'imageVariation',
            $userPath[0],
            $userPath[1],
            $userPath[2],
            $user,
            $imageIdentifier[0],
            $imageIdentifier[1],
            $imageIdentifier[2],
            $imageIdentifier,
        ];

        if (null !== $width) {
            $parts[] = $width;
        }

        return implode('/', $parts);
    }
}
