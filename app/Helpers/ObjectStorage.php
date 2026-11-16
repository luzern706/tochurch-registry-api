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
 *   AWS_URL=https://kr1-api-object-storage.nhncloudservice.com/v1/AUTH_<프로젝트ID>/<컨테이너>
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
     * 파일의 공개 기본 URL. NHN 모드(AWS_ENDPOINT 설정)에서 AWS_URL 이 있을 때만 값이 있다.
     *
     * NHN Object Storage 는 S3 스타일 URL(https://{endpoint}/{bucket}/{key})로는 익명 읽기가 안 되고
     * 공개 컨테이너 URL(https://{endpoint}/v1/AUTH_{프로젝트ID}/{컨테이너}/{key})로만 열린다(2026-11-11 실측).
     * 그래서 AWS_URL 에 후자의 기본 주소(끝에 / 없이)를 넣는다.
     *
     *   AWS_URL=https://kr1-api-object-storage.nhncloudservice.com/v1/AUTH_<프로젝트ID>/<컨테이너>
     */
    public static function publicBaseUrl(): ?string
    {
        if (empty(config('filesystems.disks.s3.endpoint'))) {
            return null;
        }

        $base = trim((string) config('filesystems.disks.s3.url'));

        return $base === '' ? null : rtrim($base, '/');
    }

    /**
     * 업로드한 파일을 DB 에 저장·화면에 보여줄 URL.
     *
     * - AWS S3 (AWS_ENDPOINT 없음) : SDK 가 돌려준 ObjectURL 그대로 (기존 동작)
     * - NHN (AWS_ENDPOINT + AWS_URL) : {AWS_URL}/{키}  (키는 경로 구간별로 URL 인코딩)
     * - NHN 인데 AWS_URL 이 비어 있으면 SDK 의 ObjectURL 로 폴백 (공개 URL 로는 열리지 않음)
     */
    public static function objectUrl(string $key, ?string $sdkUrl = null): ?string
    {
        $base = self::publicBaseUrl();
        if ($base === null) {
            return $sdkUrl;
        }

        $encoded = implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));

        return $base . '/' . $encoded;
    }

    /**
     * putObject 에 붙일 Content-Type 옵션. 키의 확장자로 추정한다(추정 불가면 빈 배열).
     * 지정하지 않으면 NHN Object Storage 에서 application/octet-stream 으로 저장돼
     * 주소를 직접 열 때 이미지가 다운로드로 처리될 수 있다. putObject 인자 배열에 `+` 로 합친다.
     */
    public static function contentOptions(string $key): array
    {
        $type = \GuzzleHttp\Psr7\MimeType::fromFilename($key);

        return $type === null ? [] : ['ContentType' => $type];
    }

    /**
     * 저장된 파일 URL 에서 오브젝트 키를 뽑는다.
     *
     * - 공개 기본 URL  (AWS_URL/uploads/a.png, NHN 모드)               → uploads/a.png
     * - 가상 호스트 방식 (https://{bucket}.s3.../uploads/a.png)  → uploads/a.png
     * - 경로 방식        (https://{endpoint}/{bucket}/uploads/a.png) → uploads/a.png (버킷명 제거)
     *
     * 호스트가 다른 옛 주소(예: TOAST api-storage.cloud.toast.com/v1/AUTH_xx/ghstorage/...)는
     * 공개 기본 URL 로 시작하지 않으므로 아래 기존 규칙으로 처리된다(새 버킷의 객체로 오인하지 않는다).
     */
    public static function keyFromUrl(string $url): ?string
    {
        $base = self::publicBaseUrl();
        if ($base !== null && str_starts_with($url, $base . '/')) {
            $rest = substr($url, strlen($base) + 1);
            $rest = preg_split('/[?#]/', $rest, 2)[0];

            return $rest === '' ? null : rawurldecode($rest);
        }

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
