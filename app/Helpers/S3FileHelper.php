<?php

namespace App\Helpers;

use App\Constants\FileUploadConstants;
use Illuminate\Http\UploadedFile;

/**
 * S3 파일 업로드/삭제 헬퍼 (02_gh_admin_api ChurchService 패턴과 동일한 raw AWS SDK 방식)
 */
class S3FileHelper
{
    public static function validate(UploadedFile $file, array $allowedExtensions, string $label): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowedExtensions, true)) {
            return "{$label}: 허용되지 않는 파일 형식입니다. (" . strtoupper(implode(', ', $allowedExtensions)) . "만 가능)";
        }

        if ($file->getSize() > FileUploadConstants::MAX_FILE_SIZE) {
            return "{$label}: 파일 크기가 10MB를 초과합니다.";
        }

        return null;
    }

    /**
     * 로컬 임시 저장 → S3 업로드. 반환: ['s3_key','s3_url','local_path','file_size']
     * 실패 시 \RuntimeException('UPLOAD_FAILED') throw.
     */
    public static function upload(UploadedFile $file, string $s3KeyPrefix, string $baseFileName): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $destinationPath = public_path('uploads');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }
        $hashedName = $file->hashName();
        $localPath = $destinationPath . '/' . $hashedName;
        $file->move($destinationPath, $hashedName);

        $s3Key = "{$s3KeyPrefix}/{$baseFileName}.{$ext}";

        try {
            $client = self::makeClient();
            $result = $client->putObject([
                'Bucket'     => FileUploadConstants::S3_BUCKET,
                'Key'        => $s3Key,
                'SourceFile' => $localPath,
            ]);
            $s3Url = $result['ObjectURL'] ?? null;
            $client->headObject(['Bucket' => FileUploadConstants::S3_BUCKET, 'Key' => $s3Key]);
        } catch (\Throwable $e) {
            @unlink($localPath);
            LogHelper::logWrite("[S3FileHelper] upload error: " . $e->getMessage(), "file");
            throw new \RuntimeException('UPLOAD_FAILED');
        }

        if (!$s3Url) {
            @unlink($localPath);
            throw new \RuntimeException('UPLOAD_FAILED');
        }

        return [
            's3_key'     => $s3Key,
            's3_url'     => $s3Url,
            'local_path' => $localPath,
            'file_size'  => filesize($localPath),
            'ext'        => $ext,
        ];
    }

    public static function cleanupLocalTemp(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }

    public static function deleteFromS3(string $s3Key): void
    {
        try {
            self::makeClient()->deleteObject([
                'Bucket' => FileUploadConstants::S3_BUCKET,
                'Key'    => $s3Key,
            ]);
        } catch (\Throwable $e) {
            LogHelper::logWrite("[S3FileHelper] delete error: {$s3Key} - " . $e->getMessage(), "file");
        }
    }

    public static function extractS3KeyFromUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        return isset($parsed['path']) ? ltrim($parsed['path'], '/') : null;
    }

    private static function makeClient(): \Aws\S3\S3Client
    {
        return new \Aws\S3\S3Client([
            'version'     => 'latest',
            'region'      => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'scheme'      => 'http',
        ]);
    }
}
