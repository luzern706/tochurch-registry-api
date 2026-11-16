<?php

namespace Tests\Unit;

use App\Helpers\ObjectStorage;
use Tests\TestCase;

class ObjectStorageTest extends TestCase
{
    private const BUCKET = 'sample-bucket';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.s3.key'      => 'test-key',
            'filesystems.disks.s3.secret'   => 'test-secret',
            'filesystems.disks.s3.region'   => 'us-east-1',
            'filesystems.disks.s3.bucket'   => self::BUCKET,
            'filesystems.disks.s3.endpoint' => null,
            'filesystems.disks.s3.url'      => null,
            'filesystems.disks.s3.use_path_style_endpoint' => false,
        ]);
    }

    private const NHN_BASE = 'https://storage.example.test/v1/AUTH_sample/sample-container';

    private function useNhn(?string $url = self::NHN_BASE): void
    {
        config([
            'filesystems.disks.s3.endpoint' => 'https://storage.example.test',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
            'filesystems.disks.s3.url' => $url,
        ]);
    }

    public function test_object_url_returns_sdk_url_for_aws(): void
    {
        // AWS_URL 이 있어도 AWS_ENDPOINT 가 없으면 기존 동작(SDK ObjectURL)을 유지한다
        config(['filesystems.disks.s3.url' => 'https://ignored.example.test']);
        $sdk = 'https://' . self::BUCKET . '.s3.us-east-1.amazonaws.com/uploads/a.png';

        $this->assertNull(ObjectStorage::publicBaseUrl());
        $this->assertSame($sdk, ObjectStorage::objectUrl('uploads/a.png', $sdk));
    }

    public function test_object_url_uses_public_base_url_in_nhn_mode(): void
    {
        $this->useNhn(self::NHN_BASE . '/');   // 끝의 / 는 무시된다

        $this->assertSame(self::NHN_BASE, ObjectStorage::publicBaseUrl());
        $this->assertSame(
            self::NHN_BASE . '/uploads/a.png',
            ObjectStorage::objectUrl('uploads/a.png', 'https://storage.example.test/' . self::BUCKET . '/uploads/a.png')
        );
    }

    public function test_object_url_encodes_each_key_segment(): void
    {
        $this->useNhn();

        $this->assertSame(
            self::NHN_BASE . '/uploads/%ED%95%9C%20%EA%B8%80.png',
            ObjectStorage::objectUrl('uploads/한 글.png')
        );
    }

    public function test_object_url_falls_back_to_sdk_url_when_nhn_has_no_public_base_url(): void
    {
        $this->useNhn(null);
        $sdk = 'https://storage.example.test/' . self::BUCKET . '/uploads/a.png';

        $this->assertNull(ObjectStorage::publicBaseUrl());
        $this->assertSame($sdk, ObjectStorage::objectUrl('uploads/a.png', $sdk));
    }

    public function test_key_from_public_base_url_in_nhn_mode(): void
    {
        $this->useNhn();

        $this->assertSame('uploads/a.png', ObjectStorage::keyFromUrl(self::NHN_BASE . '/uploads/a.png'));
        $this->assertSame('uploads/한 글.png', ObjectStorage::keyFromUrl(self::NHN_BASE . '/uploads/%ED%95%9C%20%EA%B8%80.png'));
        $this->assertSame('uploads/a.png', ObjectStorage::keyFromUrl(self::NHN_BASE . '/uploads/a.png?x=1'));
    }

    public function test_key_from_object_url_round_trips(): void
    {
        $this->useNhn();

        $key = 'uploads/06/2026/한 글 파일.png';
        $this->assertSame($key, ObjectStorage::keyFromUrl(ObjectStorage::objectUrl($key)));
    }

    public function test_key_from_other_host_url_is_not_treated_as_public_base_url(): void
    {
        // 옛 TOAST 주소는 공개 기본 URL 로 시작하지 않으므로 새 컨테이너 키로 오인하지 않는다
        $this->useNhn();
        $url = 'https://old.example.test/v1/AUTH_oldtenant/ghstorage/uploads/a.png';

        $this->assertSame('v1/AUTH_oldtenant/ghstorage/uploads/a.png', ObjectStorage::keyFromUrl($url));
    }

    public function test_key_is_null_for_public_base_url_without_key(): void
    {
        $this->useNhn();

        $this->assertNull(ObjectStorage::keyFromUrl(self::NHN_BASE . '/'));
    }

    public function test_bucket_falls_back_to_default_when_not_configured(): void
    {
        config(['filesystems.disks.s3.bucket' => null]);
        $this->assertSame(ObjectStorage::DEFAULT_BUCKET, ObjectStorage::bucket());

        config(['filesystems.disks.s3.bucket' => 'other-bucket']);
        $this->assertSame('other-bucket', ObjectStorage::bucket());
    }

    public function test_key_from_virtual_hosted_aws_url(): void
    {
        $url = 'https://' . self::BUCKET . '.s3.us-east-1.amazonaws.com/uploads/a.png';
        $this->assertSame('uploads/a.png', ObjectStorage::keyFromUrl($url));
    }

    public function test_key_from_path_style_url_strips_bucket_name(): void
    {
        $url = 'https://storage.example.test/' . self::BUCKET . '/uploads/a.png';
        $this->assertSame('uploads/a.png', ObjectStorage::keyFromUrl($url));
    }

    public function test_key_keeps_path_when_it_only_looks_like_bucket_prefix_on_virtual_host(): void
    {
        // 가상 호스트 방식에서는 키가 버킷명으로 시작해도 그대로 둔다
        $url = 'https://' . self::BUCKET . '.s3.us-east-1.amazonaws.com/' . self::BUCKET . '/a.png';
        $this->assertSame(self::BUCKET . '/a.png', ObjectStorage::keyFromUrl($url));
    }

    public function test_key_from_legacy_url_with_other_bucket_is_unchanged(): void
    {
        config(['filesystems.disks.s3.bucket' => 'new-bucket']);
        $url = 'https://' . self::BUCKET . '.s3.us-east-1.amazonaws.com/uploads/old.png';
        $this->assertSame('uploads/old.png', ObjectStorage::keyFromUrl($url));
    }

    public function test_key_is_null_when_url_has_no_path(): void
    {
        $this->assertNull(ObjectStorage::keyFromUrl('https://example.com'));
    }

    public function test_client_uses_aws_when_no_endpoint(): void
    {
        $client = ObjectStorage::client('https');

        $this->assertStringContainsString('amazonaws.com', (string) $client->getEndpoint());
        $this->assertSame('us-east-1', $client->getRegion());
    }

    public function test_client_uses_custom_endpoint_for_s3_compatible_storage(): void
    {
        config([
            'filesystems.disks.s3.endpoint' => 'https://storage.example.test',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
            'filesystems.disks.s3.region' => 'sample-region',
        ]);

        $client = ObjectStorage::client('https');

        $this->assertSame('storage.example.test', $client->getEndpoint()->getHost());
        $this->assertSame('sample-region', $client->getRegion());
    }

    public function test_content_options_guess_type_from_key_extension(): void
    {
        $this->assertSame(['ContentType' => 'image/png'], ObjectStorage::contentOptions('uploads/a.png'));
        $this->assertSame(['ContentType' => 'image/jpeg'], ObjectStorage::contentOptions('uploads/한글 사진.JPG'));
        $this->assertSame(['ContentType' => 'application/pdf'], ObjectStorage::contentOptions('docs/a.pdf'));
    }

    public function test_content_options_empty_when_type_unknown(): void
    {
        $this->assertSame([], ObjectStorage::contentOptions('uploads/noext'));
    }
}
