<?php declare(strict_types=1);
namespace Imbo\EventListener\ImageVariations\Storage;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Imbo\Exception\StorageException;

/**
 * AWS S3 storage adapter for the image variations event listener
 */
class S3 implements StorageInterface
{
    private S3Client $client;
    private string $bucket;

    /** @var array<string,mixed> */
    private array $params = [
        'version' => '2006-03-01',
    ];

    /**
     * Class constructor
     *
     * @param string $key
     * @param string $secret
     * @param string $bucket
     * @param string $region
     * @param array<string,mixed> $params
     */
    public function __construct(string $key, string $secret, string $bucket, string $region, array $params = [])
    {
        $clientParams = array_replace_recursive([
            'region'      => $region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ], $this->params, $params ?: []);

        $this->client = new S3Client($clientParams);
        $this->bucket = $bucket;
    }

    public function getClient(): S3Client
    {
        return $this->client;
    }

    public function storeImageVariation(string $user, string $imageIdentifier, string $blob, int $width): bool
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->getImagePath($user, $imageIdentifier, $width),
                'Body'   => $blob,
            ]);
        } catch (S3Exception $e) {
            throw new StorageException('Unable to store image', 500);
        }

        return true;
    }

    public function getImageVariation(string $user, string $imageIdentifier, int $width): ?string
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->getImagePath($user, $imageIdentifier, $width),
            ]);
        } catch (S3Exception $e) {
            if (404 === $e->getStatusCode()) {
                throw new StorageException('File not found', 404, $e);
            }

            throw new StorageException('Unable to get image variation', 500, $e);
        }

        return (string) $result->get('Body');
    }

    public function deleteImageVariations(string $user, string $imageIdentifier, int $width = null): bool
    {
        if (null !== $width) {
            try {
                $this->client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key'    => $this->getImagePath($user, $imageIdentifier, $width),
                ]);
            } catch (S3Exception $e) {
                if (404 === $e->getStatusCode()) {
                    throw new StorageException('File not found', 404, $e);
                }

                throw new StorageException('Unable to delete image variation', 500, $e);
            }

            return true;
        }

        /** @var array{Contents: array<int, array{Key: string}>} */
        $objects      = $this->client->listObjects([
            'Bucket' => $this->bucket,
            'Prefix' => $this->getImagePath($user, $imageIdentifier),
        ])->toArray();
        $keysToDelete = array_map(fn (array $object): array => ['Key' => $object['Key']], $objects['Contents'] ?? []);

        if (!empty($keysToDelete)) {
            try {
                $this->client->deleteObjects([
                    'Bucket' => $this->bucket,
                    'Delete' => ['Objects' => $keysToDelete],
                ]);
            } catch (S3Exception $e) {
                throw new StorageException('Unable to delete image variations', 500, $e);
            }
        }

        return true;
    }

    /**
     * Get the path to an image
     *
     * @param string $user The user which the image belongs to
     * @param string $imageIdentifier Image identifier
     * @param int $width Width of the image, in pixels
     * @return string
     */
    private function getImagePath(string $user, string $imageIdentifier, int $width = null): string
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
