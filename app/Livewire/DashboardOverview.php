<?php

namespace App\Livewire;

use App\Models\AdministrativeDocument;
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
        $now = now('Africa/Abidjan');
        $user = auth()->user();

        if ($user->canViewAllGares()) {
            $this->start_date = $now->copy()->subDays(7)->toDateString();
            $this->end_date = $now->toDateString();
        } else {
            $this->start_date = $now->copy()->startOfMonth()->toDateString();
            $this->end_date = $now->toDateString();
            if ($user->isChefDeGare()) {
                $this->gare_id = $user->gare_id;
            }
        }
    }

    public function applyFilters(): void
    {
        if (! auth()->user()->canViewAllGares()) {
            $now = now('Africa/Abidjan');
            $this->start_date = $now->copy()->startOfMonth()->toDateString();
            $this->end_date = $now->toDateString();
            if (auth()->user()->isChefDeGare()) {
                $this->gare_id = auth()->user()->gare_id;
            }
        }
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

        if ($user->isControleur()) {
            return $this->controleurMetrics();
        }

        [$startDate, $endDate] = $this->resolvedPeriod();
        $gareIds = $this->resolvedGareIds();

        $recettes = Recette::query()
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->whereIn('gare_id', $gareIds);

        $depenses = Depense::query()
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->whereIn('gare_id', $gareIds);

        $versements = VersementBancaire::query()
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->whereIn('gare_id', $gareIds);

        $controls = collect();
        if ($user->canViewAllGares()) {
            $controls = DailyControl::query()
                ->whereBetween('concerned_date', [$startDate, $endDate])
                ->whereIn('gare_id', $gareIds)
                ->latest('concerned_date')
                ->limit(10)
                ->with('gare')
                ->get();
        }

        $missingYesterday = DailyControl::query()
            ->whereDate('concerned_date', now('Africa/Abidjan')->subDay()->toDateString())
            ->whereIn('gare_id', $gareIds)
            ->where('is_compliant', false)
            ->with('gare')
            ->get();

        $trendDates = collect();
        $cursor = now()->parse($startDate);
        $end = now()->parse($endDate);
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
                ->whereBetween('operation_date', [$startDate, $endDate])
                ->whereIn('gare_id', $gareIds)
                ->groupBy('gare_id')
                ->with('gare')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $topDepenses = Depense::query()
                ->selectRaw('gare_id, SUM(amount) as total')
                ->whereBetween('operation_date', [$startDate, $endDate])
                ->whereIn('gare_id', $gareIds)
                ->groupBy('gare_id')
                ->with('gare')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $topSaisie = Gare::query()
                ->whereIn('id', $gareIds)
                ->withCount([
                    'recettes as recettes_count' => fn ($query) => $query->whereBetween('operation_date', [$startDate, $endDate]),
                    'depenses as depenses_count' => fn ($query) => $query->whereBetween('operation_date', [$startDate, $endDate]),
                    'versementsBancaires as versements_count' => fn ($query) => $query->whereBetween('operation_date', [$startDate, $endDate]),
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
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->unique(function ($notification) {
                $payload = (array) ($notification->payload ?? []);

                return implode('|', [
                    $notification->type,
                    $payload['verification_check_id'] ?? $payload['document_id'] ?? '',
                    $notification->concerned_date?->toDateString() ?: '',
                    collect($notification->gares ?? [])->filter()->sort()->values()->implode(','),
                    collect($notification->operations ?? [])->filter()->sort()->values()->implode(','),
                ]);
            })
            ->take(5)
            ->values();

        return [
            'mode' => 'financial',
            'user_can_view_all' => $user->canViewAllGares(),
            'period_label' => $user->canViewAllGares()
                ? 'Période filtrée'
                : 'Mois en cours du '.now('Africa/Abidjan')->startOfMonth()->format('d/m/Y').' au '.now('Africa/Abidjan')->format('d/m/Y'),
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
        ];
    }

    protected function controleurMetrics(): array
    {
        $today = now('Africa/Abidjan')->startOfDay();

        $byType = AdministrativeDocument::query()
            ->selectRaw('document_type, COUNT(*) as total')
            ->groupBy('document_type')
            ->orderBy('document_type')
            ->get();

        $critical = AdministrativeDocument::query()
            ->where('is_active', true)
            ->whereBetween('expires_at', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->count();

        $active = AdministrativeDocument::query()
            ->where('is_active', true)
            ->count();

        $recentNotifications = NotificationHistory::query()
            ->where('user_id', auth()->id())
            ->whereIn('type', ['document_expired', 'document_expiry_daily', 'document_expiry_weekly'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        return [
            'mode' => 'controleur',
            'documents_total' => AdministrativeDocument::query()->count(),
            'documents_active' => $active,
            'documents_critical' => $critical,
            'documents_by_type' => $byType,
            'recent_notifications' => $recentNotifications,
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

    protected function resolvedPeriod(): array
    {
        $user = auth()->user();

        if (! $user->canViewAllGares()) {
            $now = now('Africa/Abidjan');

            return [
                $now->copy()->startOfMonth()->toDateString(),
                $now->toDateString(),
            ];
        }

        return [$this->start_date, $this->end_date];
    }

    public function render(): View
    {
        return view('livewire.dashboard-overview');
    }
}
