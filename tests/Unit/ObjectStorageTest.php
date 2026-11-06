<?php

namespace Tests\Unit;

use App\Helpers\ObjectStorage;
use Tests\TestCase;

class ObjectStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.s3.key'      => 'test-key',
            'filesystems.disks.s3.secret'   => 'test-secret',
            'filesystems.disks.s3.region'   => 'ap-northeast-2',
            'filesystems.disks.s3.bucket'   => 'tochurchfile',
            'filesystems.disks.s3.endpoint' => null,
            'filesystems.disks.s3.use_path_style_endpoint' => false,
        ]);
    }

    public function test_bucket_falls_back_to_default_when_not_configured(): void
    {
        config(['filesystems.disks.s3.bucket' => null]);
        $this->assertSame('tochurchfile', ObjectStorage::bucket());

        config(['filesystems.disks.s3.bucket' => 'other-bucket']);
        $this->assertSame('other-bucket', ObjectStorage::bucket());
    }

    public function test_key_from_virtual_hosted_aws_url(): void
    {
        $url = 'https://tochurchfile.s3.ap-northeast-2.amazonaws.com/uploads/gh_2026.png';
        $this->assertSame('uploads/gh_2026.png', ObjectStorage::keyFromUrl($url));
    }

    public function test_key_from_path_style_url_strips_bucket_name(): void
    {
        $url = 'https://kr1-api-object-storage.nhncloudservice.com/tochurchfile/uploads/gh_2026.png';
        $this->assertSame('uploads/gh_2026.png', ObjectStorage::keyFromUrl($url));
    }

    public function test_key_keeps_path_when_it_only_looks_like_bucket_prefix_on_virtual_host(): void
    {
        // 가상 호스트 방식에서는 키가 버킷명으로 시작해도 그대로 둔다
        $url = 'https://tochurchfile.s3.ap-northeast-2.amazonaws.com/tochurchfile/a.png';
        $this->assertSame('tochurchfile/a.png', ObjectStorage::keyFromUrl($url));
    }

    public function test_key_from_legacy_url_with_other_bucket_is_unchanged(): void
    {
        config(['filesystems.disks.s3.bucket' => 'new-bucket']);
        $url = 'https://tochurchfile.s3.ap-northeast-2.amazonaws.com/uploads/old.png';
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
        $this->assertSame('ap-northeast-2', $client->getRegion());
    }

    public function test_client_uses_custom_endpoint_for_s3_compatible_storage(): void
    {
        config([
            'filesystems.disks.s3.endpoint' => 'https://kr1-api-object-storage.nhncloudservice.com',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
            'filesystems.disks.s3.region' => 'kr1',
        ]);

        $client = ObjectStorage::client('https');

        $this->assertSame('kr1-api-object-storage.nhncloudservice.com', $client->getEndpoint()->getHost());
        $this->assertSame('kr1', $client->getRegion());
    }
}
