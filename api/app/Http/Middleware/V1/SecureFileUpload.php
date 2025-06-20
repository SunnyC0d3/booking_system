<?php

namespace App\Http\Middleware\V1;

use App\Services\V1\Logger\SecurityLog;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class SecureFileUpload
{
    protected array $allowedMimeTypes = [
        'images' => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ],
        'documents' => [
            'application/pdf',
            'text/plain',
        ]
    ];

    protected array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'txt'
    ];

    protected array $maxSizes = [
        'image' => 5 * 1024 * 1024,
        'document' => 10 * 1024 * 1024,
        'default' => 2 * 1024 * 1024,
    ];

    protected array $dangerousSignatures = [
        '<?php',
        '<?=',
        '<script',
        '<html',
        'GIF89a<?php',
        '\x00\x00\x01\xBA',
    ];

    public function __construct(SecurityLog $securityLogger)
    {
        $this->securityLogger = $securityLogger;
    }

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->allFiles() as $key => $files) {
            $files = is_array($files) ? $files : [$files];

            foreach ($files as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $this->validateFile($file, $key);
                }
            }
        }

        return $next($request);
    }

    protected function validateFile(UploadedFile $file, string $fieldName): void
    {
        try {
            $this->validateFileSize($file, $fieldName);
            $this->validateFileExtension($file);
            $this->validateMimeType($file);
            $this->validateFileContent($file);
            $this->scanForThreats($file);

            $this->securityLogger->logFileUpload(request(), 'success', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'field' => $fieldName
            ]);
        } catch (Exception $e) {
            $this->securityLogger->logFileUpload(request(), 'blocked', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'field' => $fieldName,
                'reason' => $e->getMessage()
            ]);
        }
    }

    protected function validateFileSize(UploadedFile $file, string $fieldName): void
    {
        $maxSize = $this->getMaxSizeForField($fieldName);

        if ($file->getSize() > $maxSize) {
            abort(413, sprintf(
                'File too large. Maximum size is %s MB.',
                round($maxSize / 1024 / 1024, 1)
            ));
        }
    }

    protected function validateFileExtension(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $this->allowedExtensions)) {
            abort(422, sprintf(
                'File type .%s not allowed. Allowed types: %s',
                $extension,
                implode(', ', $this->allowedExtensions)
            ));
        }
    }

    protected function validateMimeType(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        $allowedMimes = array_merge(...array_values($this->allowedMimeTypes));

        if (!in_array($mimeType, $allowedMimes)) {
            abort(422, sprintf(
                'MIME type %s not allowed.',
                $mimeType
            ));
        }

        $this->validateMimeExtensionMatch($file);
    }

    protected function validateFileContent(UploadedFile $file): void
    {
        $handle = fopen($file->getPathname(), 'rb');
        if (!$handle) {
            abort(422, 'Unable to read uploaded file.');
        }

        $header = fread($handle, 1024);
        fclose($handle);

        foreach ($this->dangerousSignatures as $signature) {
            if (stripos($header, $signature) !== false) {
                abort(422, 'File contains potentially malicious content.');
            }
        }

        $this->validateMagicBytes($file, $header);
    }

    protected function validateMagicBytes(UploadedFile $file, string $header): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $magicBytes = [
            'jpg' => ['\xFF\xD8\xFF'],
            'jpeg' => ['\xFF\xD8\xFF'],
            'png' => ['\x89\x50\x4E\x47'],
            'gif' => ['\x47\x49\x46\x38'],
            'webp' => ['\x52\x49\x46\x46'],
            'pdf' => ['\x25\x50\x44\x46'],
        ];

        if (isset($magicBytes[$extension])) {
            $validMagic = false;
            foreach ($magicBytes[$extension] as $magic) {
                if (strpos($header, $magic) === 0) {
                    $validMagic = true;
                    break;
                }
            }

            if (!$validMagic) {
                abort(422, 'File header does not match file extension.');
            }
        }
    }

    protected function scanForThreats(UploadedFile $file): void
    {
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $this->scanImageForThreats($file);
        }
    }

    protected function scanImageForThreats(UploadedFile $file): void
    {
        $content = file_get_contents($file->getPathname());

        $patterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                abort(422, 'Image contains embedded malicious code.');
            }
        }
    }

    protected function getMaxSizeForField(string $fieldName): int
    {
        if (str_contains($fieldName, 'image') || str_contains($fieldName, 'logo')) {
            return $this->maxSizes['image'];
        }

        if (str_contains($fieldName, 'document') || str_contains($fieldName, 'pdf')) {
            return $this->maxSizes['document'];
        }

        return $this->maxSizes['default'];
    }

    protected function validateMimeExtensionMatch(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        $mimeExtensionMap = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'image/svg+xml' => ['svg'],
            'application/pdf' => ['pdf'],
        ];

        foreach ($mimeExtensionMap as $mime => $extensions) {
            if ($mimeType === $mime && !in_array($extension, $extensions)) {
                abort(422, 'File extension does not match MIME type.');
            }
        }
    }
}
