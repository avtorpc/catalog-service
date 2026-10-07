<?php

namespace App\Application\Import\UploadFile\Mapper;

use App\Application\Import\UploadFile\Command\UploadFileCommand;
use App\Shared\Exception\BadRequestException;
use App\Shared\Exception\ErrorCode;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class UploadFileMapper
{
    public static function fromRequest(Request $request): UploadFileCommand
    {
        $file = $request->files->get('file');

        /**
         * 1. FILE IS MISSING
         */
        if (!$file) {
            throw new BadRequestException(
                message: 'Import file is required',
                errorCode: ErrorCode::IMPORT_FILE_MISSING,
                context: [
                    'expected_field' => 'file',
                    'received_files' => array_keys($request->files->all()),
                    'content_type' => $request->headers->get('Content-Type'),
                    'uri' => $request->getRequestUri(),
                    'method' => $request->getMethod(),
                ]
            );
        }

        if (!$file instanceof UploadedFile) {
            throw new BadRequestException(
                message: 'Invalid uploaded file structure',
                errorCode: ErrorCode::IMPORT_FILE_INVALID,
                context: [
                    'type' => get_debug_type($file),
                    'expected' => UploadedFile::class,
                ]
            );
        }

        /**
         * 2. UPLOAD ERROR (PHP level)
         */
        if ($file->getError() !== UPLOAD_ERR_OK) {

            throw new BadRequestException(
                message: 'File upload failed',
                errorCode: ErrorCode::IMPORT_FILE_UPLOAD_FAILED,
                context: [
                    'php_upload_error_code' => $file->getError(),
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ]
            );
        }

        /**
         * 3. EMPTY FILE
         */
        if ($file->getSize() === 0) {
            throw new BadRequestException(
                message: 'Uploaded file is empty',
                errorCode: ErrorCode::IMPORT_FILE_EMPTY,
                context: [
                    'original_name' => $file->getClientOriginalName(),
                ]
            );
        }

        return new UploadFileCommand(
            file: $file
        );
    }
}
