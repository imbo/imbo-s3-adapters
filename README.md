# AWS S3 storage adapters for Imbo

[AWS S3](https://aws.amazon.com/s3/) storage adapters for [Imbo](https://imbo.io).

## Installation

    composer require imbo/imbo-s3-adapters

## Usage

This package provides two storage adapters for Imbo. One for the main images, and one for image variations.

```php
use Imbo\Storage\S3 as MainStorage;
use Imbo\EventListener\ImageVariations\Storage\S3 as ImageVariationStorage;

$mainAdapter           = new MainStorage($keyId, $applicationKey, $bucketId, $bucketName);
$imageVariationAdapter = new ImageVariationStorage($keyId, $applicationKey, $bucketId, $bucketName);
```

## Running integration tests

If you want to run the integration tests for this adapter you need to export the following environment variables:

- `S3_KEY`
- `S3_SECRET`
- `S3_BUCKET`
- `S3_REGION`

You will also need to copy `phpunit.xml.dist` to `phpunit.xml` and comment out or remove the part in the configuration that excludes the integration test group.

**Warning:** The integration tests will empty the specified bucket, so if you intend to run the integration tests you should create a dedicated bucket for this purpose.

## License

MIT, see [LICENSE](LICENSE).
