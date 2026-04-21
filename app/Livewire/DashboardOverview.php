<?php

namespace App\Livewire;

use App\Enums\ServiceModule;
use App\Models\AdministrativeDocument;
use App\Models\DailyControl;
use App\Models\Depense;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Models\Recette;
use App\Models\VersementBancaire;
use App\Support\ModuleContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DashboardOverview extends Component
{
    public string $start_date = '';
    public string $end_date = '';
    public ?int $gare_id = null;
    public string $module = 'gares';

    public function mount(): void
    {
        $resolvedModule = ModuleContext::fromRequest(request(), auth()->user());
        $this->module = $resolvedModule->value;

        $now = now('Africa/Abidjan');
        $user = auth()->user();

        if ($resolvedModule->supportsFinancialFlows()) {
            if ($user->canViewAllGares()) {
                $this->start_date = $now->copy()->subDays(7)->toDateString();
                $this->end_date = $now->toDateString();
            } else {
                $this->start_date = $now->copy()->startOfMonth()->toDateString();
                $this->end_date = $now->toDateString();
                if ($user->isChefDeGare() || $user->isAgentCourrierGare()) {
                    $this->gare_id = $user->gare_id;
                }
            }
        }
    }

    public function applyFilters(): void
    {
        $module = ServiceModule::from($this->module);
        if (! auth()->user()->canViewAllGares() && $module->supportsFinancialFlows()) {
            $now = now('Africa/Abidjan');
            $this->start_date = $now->copy()->startOfMonth()->toDateString();
            $this->end_date = $now->toDateString();
            if (auth()->user()->isChefDeGare() || auth()->user()->isAgentCourrierGare()) {
                $this->gare_id = auth()->user()->gare_id;
            }
        }
    }

    #[Computed]
    public function gares(): Collection
    {
        $user = auth()->user();
        $module = ServiceModule::from($this->module);
        $scope = $module->financialScope();

        $query = Gare::query()->where('is_active', true)->orderBy('name');

        if (! $user->canViewAllGares()) {
            $query->whereIn('id', $user->accessibleGareIds($scope));
        }

        return $query->get();
    }

    #[Computed]
    public function metrics(): array
    {
        $module = ServiceModule::from($this->module);

        return match ($module) {
            ServiceModule::Documents => $this->documentsMetrics(),
            ServiceModule::Rh => $this->rhMetrics(),
            default => $this->financialMetrics($module),
        };
    }

    protected function financialMetrics(ServiceModule $module): array
    {
        $user = auth()->user();
        $serviceScope = $module->financialScope() ?? 'gares';
        [$startDate, $endDate] = $this->resolvedPeriod($serviceScope);
        $gareIds = $this->resolvedGareIds($serviceScope);

        $recettes = Recette::query()
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->whereIn('gare_id', $gareIds);

        $depenses = Depense::query()
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->whereIn('gare_id', $gareIds);

        $versements = VersementBancaire::query()
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->whereIn('gare_id', $gareIds);

        $controls = collect();
        if ($user->canViewAllGares()) {
            $controls = DailyControl::query()
                ->where('service_scope', $serviceScope)
                ->whereBetween('concerned_date', [$startDate, $endDate])
                ->whereIn('gare_id', $gareIds)
                ->latest('concerned_date')
                ->limit(10)
                ->with('gare')
                ->get();
        }

        $missingYesterday = DailyControl::query()
            ->where('service_scope', $serviceScope)
            ->whereDate('concerned_date', now('Africa/Abidjan')->subDay()->toDateString())
            ->whereIn('gare_id', $gareIds)
            ->where('is_compliant', false)
            ->with('gare')
            ->get();

        $topRecettes = collect();
        $topDepenses = collect();
        $topSaisie = collect();

        if ($user->canViewAllGares()) {
            $topRecettes = Recette::query()
                ->selectRaw('gare_id, SUM(amount) as total')
                ->where('service_scope', $serviceScope)
                ->whereBetween('operation_date', [$startDate, $endDate])
                ->whereIn('gare_id', $gareIds)
                ->groupBy('gare_id')
                ->with('gare')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $topDepenses = Depense::query()
                ->selectRaw('gare_id, SUM(amount) as total')
                ->where('service_scope', $serviceScope)
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
                    'recettes as recettes_count' => fn ($query) => $query->where('service_scope', $serviceScope)->whereBetween('operation_date', [$startDate, $endDate]),
                    'depenses as depenses_count' => fn ($query) => $query->where('service_scope', $serviceScope)->whereBetween('operation_date', [$startDate, $endDate]),
                    'versementsBancaires as versements_count' => fn ($query) => $query->where('service_scope', $serviceScope)->whereBetween('operation_date', [$startDate, $endDate]),
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

        $recetteBreakdownTotals = $serviceScope === 'courrier'
            ? (object) [
                'ticket_inter_total' => 0,
                'ticket_national_total' => 0,
                'bagage_inter_total' => 0,
                'bagage_national_total' => 0,
                'total_amount' => (float) $recettes->sum('amount'),
            ]
            : $recettes->clone()->selectRaw('
                COALESCE(SUM(ticket_inter_amount), 0) as ticket_inter_total,
                COALESCE(SUM(ticket_national_amount), 0) as ticket_national_total,
                COALESCE(SUM(bagage_inter_amount), 0) as bagage_inter_total,
                COALESCE(SUM(bagage_national_amount), 0) as bagage_national_total,
                COALESCE(SUM(amount), 0) as total_amount
            ')->first();

        $recetteBreakdownByGare = $serviceScope === 'courrier'
            ? collect()
            : Recette::query()
                ->selectRaw('
                    gare_id,
                    COALESCE(SUM(ticket_inter_amount), 0) as ticket_inter_total,
                    COALESCE(SUM(ticket_national_amount), 0) as ticket_national_total,
                    COALESCE(SUM(bagage_inter_amount), 0) as bagage_inter_total,
                    COALESCE(SUM(bagage_national_amount), 0) as bagage_national_total,
                    COALESCE(SUM(amount), 0) as total_amount
                ')
                ->where('service_scope', $serviceScope)
                ->whereBetween('operation_date', [$startDate, $endDate])
                ->whereIn('gare_id', $gareIds)
                ->groupBy('gare_id')
                ->with('gare')
                ->orderByDesc('total_amount')
                ->get();

        $recentNotifications = NotificationHistory::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->filter(function ($notification) use ($module) {
                $ops = collect($notification->operations ?? []);
                return $ops->isEmpty() || $ops->contains($module->value) || ! $ops->intersect(['gares', 'courrier'])->isNotEmpty();
            })
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

        $trendWeekly = $this->buildWeeklyTrend($serviceScope, $startDate, $endDate, $gareIds);

        return [
            'mode' => 'financial',
            'module' => $module,
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
            'trend' => $trendWeekly,
            'trend_chart' => $this->buildTrendChart($trendWeekly),
            'top_recettes' => $topRecettes,
            'top_depenses' => $topDepenses,
            'top_saisie' => $topSaisie,
            'recette_breakdown_totals' => $recetteBreakdownTotals,
            'recette_breakdown_by_gare' => $recetteBreakdownByGare,
            'recent_notifications' => $recentNotifications,
        ];
    }

    protected function buildWeeklyTrend(string $serviceScope, string $startDate, string $endDate, array $gareIds): Collection
    {
        $start = Carbon::parse($startDate);
        $monthStart = $start->copy()->startOfMonth();
        $monthEnd = Carbon::parse($endDate)->endOfDay();

        $segments = collect(range(1, 4))->map(function (int $segment) use ($serviceScope, $gareIds, $monthStart, $monthEnd) {
            $segmentStart = $monthStart->copy()->addDays(($segment - 1) * 7)->startOfDay();
            $segmentEnd = $segment === 4
                ? $monthEnd->copy()
                : $segmentStart->copy()->addDays(6)->endOfDay();

            return [
                'label' => 'S'.$segment,
                'recettes' => (float) Recette::query()
                    ->where('service_scope', $serviceScope)
                    ->whereBetween('operation_date', [$segmentStart->toDateString(), $segmentEnd->toDateString()])
                    ->whereIn('gare_id', $gareIds)
                    ->sum('amount'),
                'depenses' => (float) Depense::query()
                    ->where('service_scope', $serviceScope)
                    ->whereBetween('operation_date', [$segmentStart->toDateString(), $segmentEnd->toDateString()])
                    ->whereIn('gare_id', $gareIds)
                    ->sum('amount'),
                'versements' => (float) VersementBancaire::query()
                    ->where('service_scope', $serviceScope)
                    ->whereBetween('operation_date', [$segmentStart->toDateString(), $segmentEnd->toDateString()])
                    ->whereIn('gare_id', $gareIds)
                    ->sum('amount'),
            ];
        });

        return $segments;
    }

    protected function buildTrendChart(Collection $rows): array
    {
        $rows = $rows->values();
        $max = max(1, (float) $rows->flatMap(fn ($row) => [$row['recettes'], $row['depenses'], $row['versements']])->max());

        $buildPoints = function (string $key) use ($rows, $max) {
            $count = max(1, $rows->count() - 1);
            return $rows->map(function ($row, $index) use ($count, $key, $max) {
                $x = 20 + ($index * (260 / $count));
                $y = 140 - (((float) $row[$key] / $max) * 110);
                return round($x, 2).','.round($y, 2);
            })->implode(' ');
        };

        return [
            'recettes' => $buildPoints('recettes'),
            'depenses' => $buildPoints('depenses'),
            'versements' => $buildPoints('versements'),
            'max' => $max,
        ];
    }

    protected function documentsMetrics(): array
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

    protected function rhMetrics(): array
    {
        $employees = Employee::query();
        $documents = EmployeeDocument::query();

        return [
            'mode' => 'rh',
            'employees_total' => $employees->count(),
            'employees_active' => $employees->clone()->where('employment_status', 'active')->count(),
            'accounts_pending_activation' => Employee::query()
                ->whereHas('user', fn ($query) => $query->where('is_active', false))
                ->count(),
            'documents_total' => $documents->count(),
            'documents_expiring' => $documents->clone()
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count(),
            'employees_by_department' => Employee::query()
                ->selectRaw('department_id, COUNT(*) as total')
                ->with('department')
                ->groupBy('department_id')
                ->get(),
            'recent_employees' => Employee::query()->with('department')->latest()->limit(5)->get(),
        ];
    }

    protected function resolvedGareIds(?string $scope = null): array
    {
        $user = auth()->user();

        if ($this->gare_id) {
            return array_values(array_intersect($user->accessibleGareIds($scope), [$this->gare_id]));
        }

        return $user->accessibleGareIds($scope);
    }

    protected function resolvedPeriod(?string $scope = null): array
    {
        $user = auth()->user();

        if (! $user->canViewAllGares() && $scope) {
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
