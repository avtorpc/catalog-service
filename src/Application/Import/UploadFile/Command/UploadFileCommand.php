<?php

namespace App\Application\Import\UploadFile\Command;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadFileCommand
{
    public function __construct(
        public UploadedFile $file
    ) {}
}
