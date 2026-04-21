<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\UploadedFileName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeDocumentController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->canAccessRhModule() && ! $request->user()->isPersonnelTsr(), 403);

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:180'],
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $file = $request->file('document');
        $storedName = UploadedFileName::build($data['label'] ?: $data['document_type'], $file);
        $path = $file->storeAs('employee-documents/'.$employee->id, $storedName, 'private');

        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'document_type' => $data['document_type'],
            'label' => $data['label'] ?? null,
            'original_name' => $storedName,
            'file_name' => $storedName,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'disk' => 'private',
            'path' => $path,
            'expires_at' => $data['expires_at'] ?? null,
            'uploaded_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Document RH ajouté au dossier.');
    }

    public function destroy(Request $request, Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsableRh(), 403);
        abort_unless($document->employee_id === $employee->id, 404);

        \Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document RH supprimé.');
    }
}
