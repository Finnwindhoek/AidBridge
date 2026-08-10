<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentUploadRequest;
use App\Models\Application;
use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\Document\DocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentStorageService $storage,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function store(DocumentUploadRequest $request, Application $application): RedirectResponse
    {
        // Authorising against a fresh Document bound to this application means the
        // policy sees the real ownership chain.
        $this->authorize('create', new Document(['application_id' => $application->id]));

        if (! $application->isEditable()) {
            return back()->withErrors(['file' => 'Documents cannot be changed after submission.']);
        }

        try {
            $this->storage->store($application, $request->file('file'), $request->validated('document_type'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('status', 'Document uploaded.');
    }

    /**
     * Signed-URL download. The signature stops link tampering; the policy check
     * stops an authenticated user from reading someone else's evidence even with a
     * valid signature.
     */
    public function download(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        try {
            return $this->storage->download($document);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->storage->delete($document);

        return back()->with('status', 'Document removed.');
    }

    /** Admin marks a document as checked against the applicant's identity. */
    public function verify(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('verify', $document);

        $data = $request->validate([
            'decision' => ['required', 'in:verify,reject'],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:255'],
        ]);

        $verified = $data['decision'] === 'verify';

        // forceFill: the verification columns are not fillable, so an applicant can
        // never mark their own evidence as verified by posting extra fields.
        $document->forceFill([
            'verified_at' => $verified ? now() : null,
            'verified_by' => $verified ? $request->user()->id : null,
            'rejection_reason' => $verified ? null : $data['rejection_reason'],
        ])->save();

        $this->auditLogger->log(
            $verified ? 'document.verified' : 'document.rejected',
            $document,
            ['reason' => $data['rejection_reason'] ?? null]
        );

        return back()->with('status', $verified ? 'Document verified.' : 'Document rejected.');
    }
}
