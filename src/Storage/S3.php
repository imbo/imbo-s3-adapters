<?php declare(strict_types=1);

namespace Imbo\Storage;

use Aws\Credentials\Credentials;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use DateTime;
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

    public function store(string $user, string $imageIdentifier, string $imageData): true
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $this->getImagePath($user, $imageIdentifier),
                'Body' => $imageData,
            ]);
        } catch (S3Exception $e) {
            throw new StorageException('Unable to store image', 500, $e);
        }

        return true;
    }

    public function delete(string $user, string $imageIdentifier): true
    {
        if (!$this->imageExists($user, $imageIdentifier)) {
            throw new StorageException('File not found', 404);
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key' => $this->getImagePath($user, $imageIdentifier),
            ]);
        } catch (S3Exception $e) {
            throw new StorageException('Unable to delete image', 500, $e);
        }

        return true;
    }

    public function getImage(string $user, string $imageIdentifier): ?string
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucketName,
                'Key' => $this->getImagePath($user, $imageIdentifier),
            ]);
        } catch (S3Exception $e) {
            if (404 === $e->getStatusCode()) {
                throw new StorageException('File not found', 404, $e);
            }

            throw new StorageException('Unable to get image', 500, $e);
        }

        /** @var ?Stream */
        $body = $result->get('Body');

        return $body ? (string) $body : null;
    }

    public function getLastModified(string $user, string $imageIdentifier): DateTime
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucketName,
                'Key' => $this->getImagePath($user, $imageIdentifier),
            ]);
        } catch (S3Exception $e) {
            if (404 === $e->getStatusCode()) {
                throw new StorageException('File not found', 404, $e);
            }

            throw new StorageException('Unable to get image metadata', 500, $e);
        }

        $lm = $result->get('LastModified');

        if (!$lm instanceof DateTime) {
            throw new StorageException('Unable to get image metadata', 500);
        }

        return $lm;
    }

    public function getStatus(): bool
    {
        try {
            /** @var array{'@metadata'?:array{statusCode?:int}} */
            $result = $this->client->headBucket([
                'Bucket' => $this->bucketName,
            ]);
        } catch (S3Exception $e) {
            return false;
        }

        return 200 === ($result['@metadata']['statusCode'] ?? null);
    }

    public function imageExists(string $user, string $imageIdentifier): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucketName,
                'Key' => $this->getImagePath($user, $imageIdentifier),
            ]);
        } catch (S3Exception $e) {
            return false;
        }

        return true;
    }

    protected function getImagePath(string $user, string $imageIdentifier): string
    {
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
