<?php

namespace App\Services\Document;

use App\Models\Application;
use App\Models\Document;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Handles evidence uploads for Module 2.
 *
 * Every file lands on the `private` disk, outside the web root, and is only ever
 * served back through a signed, policy-checked route.
 */
class DocumentStorageService
{
    private const DISK = 'private';

    /** Extensions accepted regardless of what the client claims the MIME type is. */
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'pdf'];

    private const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'application/pdf'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(Application $application, UploadedFile $file, string $documentType): Document
    {
        $this->assertFileIsSafe($file);

        // The stored name is generated, never derived from user input, so a
        // filename like "../../.env" or "shell.php" cannot influence the path.
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;
        $directory = "documents/{$application->id}";

        $path = $file->storeAs($directory, $filename, self::DISK);

        if ($path === false) {
            throw new RuntimeException('The document could not be saved. Please try again.');
        }

        $document = $application->documents()->create([
            'document_type' => $documentType,
            'file_path' => $path,
            // Sanitised for display only; it never touches the filesystem path.
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 120, ''),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);

        $this->auditLogger->log('document.uploaded', $document, [
            'application' => $application->reference,
            'document_type' => $documentType,
            'size_bytes' => $document->size_bytes,
        ]);

        return $document;
    }

    public function delete(Document $document): void
    {
        Storage::disk(self::DISK)->delete($document->file_path);

        $this->auditLogger->log('document.deleted', $document, [
            'application_id' => $document->application_id,
            'document_type' => $document->document_type,
        ]);

        $document->delete();
    }

    /** Streams the file back to an authorised viewer without exposing its path. */
    public function download(Document $document)
    {
        if (! Storage::disk(self::DISK)->exists($document->file_path)) {
            throw new RuntimeException('The stored document is no longer available.');
        }

        $this->auditLogger->log('document.downloaded', $document);

        return Storage::disk(self::DISK)->download(
            $document->file_path,
            $document->original_name,
            [
                // Stops a crafted SVG/HTML payload from executing in the viewer's
                // origin if it somehow slipped past validation.
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ]
        );
    }

    /**
     * Defence in depth behind the Form Request rules: re-checks the real content
     * type on disk rather than trusting the browser-supplied header.
     */
    private function assertFileIsSafe(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('The upload did not complete successfully.');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Only PNG, JPG and PDF files are accepted.');
        }

        // getMimeType() inspects the file's magic bytes, unlike getClientMimeType().
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new RuntimeException('The file contents do not match an accepted image or PDF format.');
        }
    }
}
