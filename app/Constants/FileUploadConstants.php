<?php

namespace App\Constants;

class FileUploadConstants
{
    public const S3_BUCKET = 'tochurchfile';

    public const ALLOWED_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'svg'];
    public const ALLOWED_DOCUMENT_EXTENSIONS = ['png', 'jpg', 'jpeg', 'pdf'];

    public const MAX_FILE_SIZE = 10 * 1024 * 1024;
}
