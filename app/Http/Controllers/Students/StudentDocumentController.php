<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentDocumentRequest;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents live on the "secure_documents" disk (private, not web-exposed)
 * and are only ever served through download() below, which re-checks the
 * students.view permission on every request rather than relying on a
 * guessable public URL.
 */
class StudentDocumentController extends Controller
{
    public function store(StoreStudentDocumentRequest $request, Student $student): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('student-documents/'.$student->id, 'secure_documents');

        $document = $student->documents()->create([
            'document_type' => $request->string('document_type'),
            'title' => $request->string('title'),
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLogger::log('upload_document', 'students', $student->id, null, ['document_id' => $document->id, 'title' => $document->title]);

        return back()->with('status', __('app.document_uploaded'));
    }

    public function download(Student $student, StudentDocument $document): StreamedResponse
    {
        abort_unless(request()->user()->can('students.view'), 403);
        abort_unless($document->student_id === $student->id, 404);

        AuditLogger::log('download_document', 'students', $student->id, null, ['document_id' => $document->id]);

        return Storage::disk('secure_documents')->download($document->file_path, $document->original_filename);
    }

    public function destroy(Student $student, StudentDocument $document): RedirectResponse
    {
        abort_unless(request()->user()->can('students.manage'), 403);
        abort_unless($document->student_id === $student->id, 404);

        Storage::disk('secure_documents')->delete($document->file_path);
        $document->delete();

        AuditLogger::log('delete_document', 'students', $student->id, ['document_id' => $document->id]);

        return back()->with('status', __('app.document_deleted'));
    }
}
