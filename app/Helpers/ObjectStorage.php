<?php

namespace App\Helpers;

use Aws\S3\S3Client;

/**
 * S3 호환 오브젝트 스토리지(AWS S3, NHN Cloud Object Storage) 접속 공통 헬퍼.
 *
 * .env 의 AWS_ENDPOINT 가 비어 있으면 기존과 동일하게 AWS S3 로 동작한다.
 * NHN Cloud Object Storage 로 옮길 때는 .env 만 바꾼다.
 *
 *   AWS_ENDPOINT=https://kr1-api-object-storage.nhncloudservice.com
 *   AWS_USE_PATH_STYLE_ENDPOINT=true
 *   AWS_BUCKET=<버킷명>
 *   AWS_DEFAULT_REGION=kr1
 */
class ObjectStorage
{
    /** AWS_BUCKET 이 설정되지 않았을 때 쓰는 기존 버킷명 */
    public const DEFAULT_BUCKET = 'tochurchfile';

    public static function bucket(): string
    {
        return (string) (config('filesystems.disks.s3.bucket') ?: self::DEFAULT_BUCKET);
    }

    /**
     * @param string $scheme 엔드포인트가 없을 때(AWS S3) 쓰는 기존 scheme (http|https)
     */
    public static function client(string $scheme = 'https'): S3Client
    {
        $options = [
            'version'     => 'latest',
            'region'      => config('filesystems.disks.s3.region'),
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
            'scheme'      => $scheme,
        ];

        $endpoint = config('filesystems.disks.s3.endpoint');
        if (!empty($endpoint)) {
            $options['endpoint'] = $endpoint;
            $options['use_path_style_endpoint'] = (bool) config('filesystems.disks.s3.use_path_style_endpoint', false);
        }

        return new S3Client($options);
    }

    /**
     * 저장된 파일 URL 에서 오브젝트 키를 뽑는다.
     *
     * - 가상 호스트 방식 (https://{bucket}.s3.../uploads/a.png)  → uploads/a.png
     * - 경로 방식        (https://{endpoint}/{bucket}/uploads/a.png) → uploads/a.png (버킷명 제거)
     */
    public static function keyFromUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        if (!isset($parsed['path'])) {
            return null;
        }

        $key    = ltrim($parsed['path'], '/');
        $bucket = self::bucket();
        $host   = $parsed['host'] ?? '';

        if (!str_starts_with($host, $bucket . '.') && str_starts_with($key, $bucket . '/')) {
            $key = substr($key, strlen($bucket) + 1);
        }

        return $key;
    }
}
