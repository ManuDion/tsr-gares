<?php

namespace App\Livewire;

use App\Models\DailyControl;
use App\Models\Depense;
use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Models\Recette;
use App\Models\VersementBancaire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DashboardOverview extends Component
{
    public string $start_date = '';
    public string $end_date = '';
    public ?int $gare_id = null;

    public function mount(): void
    {
        $this->start_date = now()->subDays(7)->toDateString();
        $this->end_date = now()->toDateString();

        if (auth()->user()->isChefDeGare()) {
            $this->gare_id = auth()->user()->gare_id;
        }
    }

    public function applyFilters(): void
    {
        // Rafraîchissement Livewire volontaire.
    }

    #[Computed]
    public function gares(): Collection
    {
        $user = auth()->user();

        $query = Gare::query()->where('is_active', true)->orderBy('name');

        if (! $user->canViewAllGares()) {
            $query->whereIn('id', $user->accessibleGareIds());
        }

        return $query->get();
    }

    #[Computed]
    public function metrics(): array
    {
        $user = auth()->user();
        $gareIds = $this->resolvedGareIds();

        $recettes = Recette::query()
            ->whereBetween('operation_date', [$this->start_date, $this->end_date])
            ->whereIn('gare_id', $gareIds);

        $depenses = Depense::query()
            ->whereBetween('operation_date', [$this->start_date, $this->end_date])
            ->whereIn('gare_id', $gareIds);

        $versements = VersementBancaire::query()
            ->whereBetween('operation_date', [$this->start_date, $this->end_date])
            ->whereIn('gare_id', $gareIds);

        $controls = DailyControl::query()
            ->whereBetween('concerned_date', [$this->start_date, $this->end_date])
            ->whereIn('gare_id', $gareIds)
            ->latest('concerned_date')
            ->limit(10)
            ->with('gare')
            ->get();

        $missingYesterday = DailyControl::query()
            ->whereDate('concerned_date', now('Africa/Abidjan')->subDay()->toDateString())
            ->whereIn('gare_id', $gareIds)
            ->where('is_compliant', false)
            ->with('gare')
            ->get();

        $trendDates = collect();
        $cursor = now()->parse($this->start_date);
        $end = now()->parse($this->end_date);
        while ($cursor->lte($end)) {
            $trendDates->push($cursor->toDateString());
            $cursor->addDay();
        }

        $recettesTrend = $recettes->clone()
            ->selectRaw('DATE(operation_date) as day, SUM(amount) as total')
            ->groupBy(DB::raw('DATE(operation_date)'))
            ->pluck('total', 'day');

        $depensesTrend = $depenses->clone()
            ->selectRaw('DATE(operation_date) as day, SUM(amount) as total')
            ->groupBy(DB::raw('DATE(operation_date)'))
            ->pluck('total', 'day');

        $versementsTrend = $versements->clone()
            ->selectRaw('DATE(operation_date) as day, SUM(amount) as total')
            ->groupBy(DB::raw('DATE(operation_date)'))
            ->pluck('total', 'day');

        $trend = $trendDates->map(function ($day) use ($recettesTrend, $depensesTrend, $versementsTrend) {
            return [
                'label' => now()->parse($day)->format('d/m'),
                'recettes' => (float) ($recettesTrend[$day] ?? 0),
                'depenses' => (float) ($depensesTrend[$day] ?? 0),
                'versements' => (float) ($versementsTrend[$day] ?? 0),
            ];
        })->values();

        $topRecettes = collect();
        $topDepenses = collect();
        $topSaisie = collect();

        if ($user->canViewAllGares()) {
            $topRecettes = Recette::query()
                ->selectRaw('gare_id, SUM(amount) as total')
                ->whereBetween('operation_date', [$this->start_date, $this->end_date])
                ->whereIn('gare_id', $gareIds)
                ->groupBy('gare_id')
                ->with('gare')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $topDepenses = Depense::query()
                ->selectRaw('gare_id, SUM(amount) as total')
                ->whereBetween('operation_date', [$this->start_date, $this->end_date])
                ->whereIn('gare_id', $gareIds)
                ->groupBy('gare_id')
                ->with('gare')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $topSaisie = Gare::query()
                ->whereIn('id', $gareIds)
                ->withCount([
                    'recettes as recettes_count' => fn ($query) => $query->whereBetween('operation_date', [$this->start_date, $this->end_date]),
                    'depenses as depenses_count' => fn ($query) => $query->whereBetween('operation_date', [$this->start_date, $this->end_date]),
                    'versementsBancaires as versements_count' => fn ($query) => $query->whereBetween('operation_date', [$this->start_date, $this->end_date]),
                ])
                ->get()
                ->map(function ($gare) {
                    $gare->saisie_total = $gare->recettes_count + $gare->depenses_count + $gare->versements_count;
                    return $gare;
                })
                ->sortByDesc('saisie_total')
                ->take(5)
                ->values();
        }

        $recentNotifications = NotificationHistory::query()
            ->where(function ($query) use ($user) {
                if (! $user->canViewAllGares()) {
                    $query->where('user_id', $user->id);
                }
            })
            ->latest('created_at')
            ->limit(5)
            ->get();

        $recentOperations = collect([
            ...Recette::query()->whereBetween('operation_date', [$this->start_date, $this->end_date])->whereIn('gare_id', $gareIds)->latest('operation_date')->limit(5)->get()->map(fn ($row) => [
                'type' => 'Recette',
                'date' => $row->operation_date?->format('d/m/Y'),
                'gare' => $row->gare?->name,
                'amount' => (float) $row->amount,
            ])->all(),
            ...Depense::query()->whereBetween('operation_date', [$this->start_date, $this->end_date])->whereIn('gare_id', $gareIds)->latest('operation_date')->limit(5)->get()->map(fn ($row) => [
                'type' => 'Dépense',
                'date' => $row->operation_date?->format('d/m/Y'),
                'gare' => $row->gare?->name,
                'amount' => (float) $row->amount,
            ])->all(),
            ...VersementBancaire::query()->whereBetween('operation_date', [$this->start_date, $this->end_date])->whereIn('gare_id', $gareIds)->latest('operation_date')->limit(5)->get()->map(fn ($row) => [
                'type' => 'Versement',
                'date' => $row->operation_date?->format('d/m/Y'),
                'gare' => $row->gare?->name,
                'amount' => (float) $row->amount,
            ])->all(),
        ])->sortByDesc('date')->take(8)->values();

        return [
            'user_can_view_all' => $user->canViewAllGares(),
            'recettes_total' => (float) $recettes->sum('amount'),
            'depenses_total' => (float) $depenses->sum('amount'),
            'versements_total' => (float) $versements->sum('amount'),
            'recettes_count' => $recettes->count(),
            'depenses_count' => $depenses->count(),
            'versements_count' => $versements->count(),
            'controls' => $controls,
            'missing_yesterday' => $missingYesterday,
            'trend' => $trend,
            'top_recettes' => $topRecettes,
            'top_depenses' => $topDepenses,
            'top_saisie' => $topSaisie,
            'recent_notifications' => $recentNotifications,
            'recent_operations' => $recentOperations,
        ];
    }

    protected function resolvedGareIds(): array
    {
        $user = auth()->user();

        if ($this->gare_id) {
            return array_values(array_intersect($user->accessibleGareIds(), [$this->gare_id]));
        }

        return $user->accessibleGareIds();
    }

    public function render(): View
    {
        return view('livewire.dashboard-overview');
    }
}
