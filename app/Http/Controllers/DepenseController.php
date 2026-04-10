<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepenseRequest;
use App\Models\Depense;
use App\Services\AccessScopeService;
use App\Services\DocumentAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DepenseController extends Controller
{
    public function __construct(
        protected AccessScopeService $access,
        protected DocumentAnalysisService $analysis
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Depense::class);
        $user = $request->user();

        $query = Depense::query()
            ->with(['gare', 'creator', 'justificatives'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id');

        $this->access->scopeForUser($query, $user);

        $query->when($request->filled('gare_id'), fn ($q) => $q->where('gare_id', $request->integer('gare_id')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('operation_date', '>=', $request->date('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('operation_date', '<=', $request->date('end_date')));

        return view('depenses.index', [
            'depenses' => $query->paginate(15)->withQueryString(),
            'gares' => $this->access->availableGares($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Depense::class);

        return view('depenses.create', [
            'gares' => $this->access->availableGares($request->user()),
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
        ]);
    }

    public function store(StoreDepenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Depense::class);
        $user = $request->user();
        $data = $request->validated();

        $data['gare_id'] = $this->access->resolveGareIdForCreation($user, $request->integer('gare_id'));
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;

        $depense = Depense::create($data);

        if ($request->hasFile('justificatif')) {
            $file = $request->file('justificatif');
            $path = $file->store('justificatifs/depenses', env('JUSTIFICATIF_PRIVATE_DISK', 'private'));

            $piece = $depense->justificatives()->create([
                'document_type' => 'depense',
                'original_name' => $file->getClientOriginalName(),
                'file_name' => basename($path),
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'size' => $file->getSize(),
                'disk' => env('JUSTIFICATIF_PRIVATE_DISK', 'private'),
                'path' => $path,
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ]);

            $this->analysis->analyze($piece);
        }

        return redirect()->route('depenses.index')->with('status', 'Dépense enregistrée.');
    }
}
