<?php

namespace App\Http\Controllers;

use App\Models\AdministrativeDocument;
use App\Services\ActivityLogService;
use App\Services\DocumentExpiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdministrativeDocumentController extends Controller
{
    public function __construct(
        protected DocumentExpiryService $expiryService,
        protected ActivityLogService $activity
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AdministrativeDocument::class);
        $this->expiryService->ensureFreshAlerts();

        $documents = AdministrativeDocument::query()
            ->with(['uploader', 'updater'])
            ->when($request->filled('document_type'), fn ($q) => $q->where('document_type', 'like', '%'.$request->string('document_type').'%'))
            ->when($request->filled('status'), function ($q) use ($request) {
                return match ($request->string('status')->toString()) {
                    'expired' => $q->whereDate('expires_at', '<', now('Africa/Abidjan')->toDateString()),
                    'expiring' => $q->whereBetween('expires_at', [now('Africa/Abidjan')->toDateString(), now('Africa/Abidjan')->addDays(30)->toDateString()]),
                    'active' => $q->whereDate('expires_at', '>', now('Africa/Abidjan')->addDays(30)->toDateString()),
                    default => $q,
                };
            })
            ->orderBy('expires_at')
            ->paginate(15)
            ->withQueryString();

        return view('administrative-documents.index', [
            'documents' => $documents,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AdministrativeDocument::class);

        return view('administrative-documents.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AdministrativeDocument::class);

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:180'],
            'expires_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('document');
        $disk = env('JUSTIFICATIF_PRIVATE_DISK', 'private');
        $path = $file->store('administrative-documents', $disk);

        $document = AdministrativeDocument::create([
            'document_type' => $data['document_type'],
            'label' => $data['label'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'disk' => $disk,
            'path' => $path,
            'expires_at' => $data['expires_at'],
            'is_active' => true,
            'uploaded_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'last_renewed_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->expiryService->ensureFreshAlerts();

        $this->activity->log($request->user(), 'administrative_document_created', $document, 'Création d’un document administratif.', [
            'after' => $document->only(['document_type', 'label', 'original_name', 'expires_at', 'is_active']),
        ]);

        return redirect()->route('administrative-documents.index')->with('status', 'Document administratif enregistré.');
    }

    public function edit(AdministrativeDocument $administrativeDocument): View
    {
        $this->authorize('update', $administrativeDocument);

        return view('administrative-documents.edit', [
            'document' => $administrativeDocument,
        ]);
    }

    public function update(Request $request, AdministrativeDocument $administrativeDocument): RedirectResponse
    {
        $this->authorize('update', $administrativeDocument);

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:180'],
            'expires_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $before = $administrativeDocument->only(['document_type', 'label', 'original_name', 'expires_at', 'is_active']);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            if (Storage::disk($administrativeDocument->disk)->exists($administrativeDocument->path)) {
                Storage::disk($administrativeDocument->disk)->delete($administrativeDocument->path);
            }

            $path = $file->store('administrative-documents', $administrativeDocument->disk);

            $administrativeDocument->fill([
                'original_name' => $file->getClientOriginalName(),
                'file_name' => basename($path),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'path' => $path,
            ]);
        }

        $administrativeDocument->fill([
            'document_type' => $data['document_type'],
            'label' => $data['label'] ?? null,
            'expires_at' => $data['expires_at'],
            'notes' => $data['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => $request->user()->id,
            'last_renewed_at' => now(),
        ])->save();

        $this->expiryService->clearPersistentAlerts($administrativeDocument);
        $this->expiryService->ensureFreshAlerts();

        $this->activity->log($request->user(), 'administrative_document_updated', $administrativeDocument, 'Mise à jour d’un document administratif.', [
            'before' => $before,
            'after' => $administrativeDocument->fresh()->only(['document_type', 'label', 'original_name', 'expires_at', 'is_active']),
        ]);

        return redirect()->route('administrative-documents.index')->with('status', 'Document administratif mis à jour.');
    }

    public function preview(Request $request, AdministrativeDocument $administrativeDocument): BinaryFileResponse
    {
        $this->authorize('view', $administrativeDocument);

        return response()->file(
            Storage::disk($administrativeDocument->disk)->path($administrativeDocument->path),
            [
                'Content-Type' => $administrativeDocument->mime_type ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$administrativeDocument->original_name.'"',
            ]
        );
    }

    public function download(Request $request, AdministrativeDocument $administrativeDocument): StreamedResponse
    {
        $this->authorize('view', $administrativeDocument);

        return Storage::disk($administrativeDocument->disk)->download(
            $administrativeDocument->path,
            $administrativeDocument->original_name
        );
    }
}
