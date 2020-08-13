<?php declare(strict_types=1);
namespace Imbo\Storage;

use Imbo\Exception\StorageException;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use DateTime;
use Aws\Api\DateTimeResult;

/**
 * AWS S3 storage adapter
 */
class S3 implements StorageInterface {
    private S3Client $client;
    private string $bucket;

    /** @var array<string, mixed> */
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
     * @param array<string, mixed> $params
     */
    public function __construct(string $key, string $secret, string $bucket, string $region, array $params = []) {
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

    public function getClient() : S3Client {
        return $this->client;
    }

    public function store(string $user, string $imageIdentifier, string $imageData) : bool {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->getImagePath($user, $imageIdentifier),
                'Body'   => $imageData,
            ]);
        } catch (S3Exception $e) {
            throw new StorageException('Unable to store image', 500);
        }

        return true;
    }

    public function delete(string $user, string $imageIdentifier) : bool {
        if (!$this->imageExists($user, $imageIdentifier)) {
            throw new StorageException('File not found', 404);
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->getImagePath($user, $imageIdentifier),
            ]);
        } catch (S3Exception $e) {
            throw new StorageException('Unable to delete image', 500, $e);
        }

        return true;
    }

    public function getImage(string $user, string $imageIdentifier) : ?string {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->getImagePath($user, $imageIdentifier),
            ]);
        } catch (S3Exception $e) {
            if (404 === $e->getStatusCode()) {
                throw new StorageException('File not found', 404, $e);
            }

            throw new StorageException('Unable to get image', 500, $e);
        }

        return (string) $result->get('Body');
    }

    public function getLastModified(string $user, string $imageIdentifier) : DateTime {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->getImagePath($user, $imageIdentifier),
            ]);
        } catch (S3Exception $e) {
            if (404 === $e->getStatusCode()) {
                throw new StorageException('File not found', 404, $e);
            }

            throw new StorageException('Unable to get image metadata', 500, $e);
        }

        /** @var DateTimeResult */
        return $result->get('LastModified');
    }

    public function getStatus() : bool {
        try {
            $result = $this->client->headBucket([
                'Bucket' => $this->bucket,
            ]);
        } catch (S3Exception $e) {
            return false;
        }

        return 200 === ($result['@metadata']['statusCode'] ?? null);
    }

    public function imageExists(string $user, string $imageIdentifier) : bool {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->getImagePath($user, $imageIdentifier),
            ]);
        } catch (S3Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Get the path to an image
     *
     * @param string $user The user which the image belongs to
     * @param string $imageIdentifier Image identifier
     * @return string
     */
    protected function getImagePath(string $user, string $imageIdentifier) : string {
        $userPath = str_pad($user, 3, '0', STR_PAD_LEFT);

        return implode('/', [
            $userPath[0],
            $userPath[1],
            $userPath[2],
            $user,
            $imageIdentifier[0],
            $imageIdentifier[1],
            $imageIdentifier[2],
            $imageIdentifier,
        ]);
    }
}
