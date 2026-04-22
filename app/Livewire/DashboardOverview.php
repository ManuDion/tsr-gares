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
        $isCourrier = $serviceScope === 'courrier';
        [$startDate, $endDate] = $this->resolvedPeriod($serviceScope);
        $gareIds = $this->resolvedGareIds($serviceScope);
        $selectedGareId = $this->gare_id ? (int) $this->gare_id : null;
        $showGlobalSections = $user->canViewAllGares() && ! $selectedGareId;
        $monthStart = now('Africa/Abidjan')->startOfMonth();
        $monthEnd = now('Africa/Abidjan')->endOfMonth();

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

        $recettesTrend = Recette::query()
            ->selectRaw('DAY(operation_date) as day_num, SUM(amount) as total')
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereIn('gare_id', $gareIds)
            ->groupBy(DB::raw('DAY(operation_date)'))
            ->pluck('total', 'day_num');

        $depensesTrend = Depense::query()
            ->selectRaw('DAY(operation_date) as day_num, SUM(amount) as total')
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereIn('gare_id', $gareIds)
            ->groupBy(DB::raw('DAY(operation_date)'))
            ->pluck('total', 'day_num');

        $versementsTrend = VersementBancaire::query()
            ->selectRaw('DAY(operation_date) as day_num, SUM(amount) as total')
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereIn('gare_id', $gareIds)
            ->groupBy(DB::raw('DAY(operation_date)'))
            ->pluck('total', 'day_num');

        $trend = collect(range(1, 31))->map(function (int $day) use ($recettesTrend, $depensesTrend, $versementsTrend) {
            return [
                'label' => sprintf('%02d', $day),
                'recettes' => (float) ($recettesTrend[$day] ?? 0),
                'depenses' => (float) ($depensesTrend[$day] ?? 0),
                'versements' => (float) ($versementsTrend[$day] ?? 0),
            ];
        })->values();

        $weeklyWindows = [
            ['label' => 'S1', 'start' => $monthStart->copy(), 'end' => $monthStart->copy()->day(7)],
            ['label' => 'S2', 'start' => $monthStart->copy()->day(8), 'end' => $monthStart->copy()->day(14)],
            ['label' => 'S3', 'start' => $monthStart->copy()->day(15), 'end' => $monthStart->copy()->day(21)],
            ['label' => 'S4', 'start' => $monthStart->copy()->day(22), 'end' => $monthEnd->copy()],
        ];

        $weeklyComparison = collect($weeklyWindows)->map(function (array $window) use ($serviceScope, $gareIds) {
            $period = [$window['start']->toDateString(), $window['end']->toDateString()];

            return [
                'label' => $window['label'],
                'recettes' => (float) Recette::query()
                    ->where('service_scope', $serviceScope)
                    ->whereBetween('operation_date', $period)
                    ->whereIn('gare_id', $gareIds)
                    ->sum('amount'),
                'depenses' => (float) Depense::query()
                    ->where('service_scope', $serviceScope)
                    ->whereBetween('operation_date', $period)
                    ->whereIn('gare_id', $gareIds)
                    ->sum('amount'),
                'versements' => (float) VersementBancaire::query()
                    ->where('service_scope', $serviceScope)
                    ->whereBetween('operation_date', $period)
                    ->whereIn('gare_id', $gareIds)
                    ->sum('amount'),
            ];
        })->values();

        $topRecettes = collect();
        $topDepenses = collect();
        $topSaisie = collect();

        if ($showGlobalSections) {
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

        $recetteBreakdownTotals = $recettes->clone()
            ->selectRaw('
                COALESCE(SUM(ticket_inter_amount), 0) as ticket_inter_total,
                COALESCE(SUM(ticket_national_amount), 0) as ticket_national_total,
                COALESCE(SUM(bagage_inter_amount), 0) as bagage_inter_total,
                COALESCE(SUM(bagage_national_amount), 0) as bagage_national_total,
                COALESCE(SUM(amount), 0) as total_amount
            ')
            ->first();

        $recetteBreakdownByGare = Recette::query()
            ->selectRaw(
                $isCourrier
                    ? 'gare_id, COALESCE(SUM(amount), 0) as total_amount'
                    : '
                        gare_id,
                        COALESCE(SUM(ticket_inter_amount), 0) as ticket_inter_total,
                        COALESCE(SUM(ticket_national_amount), 0) as ticket_national_total,
                        COALESCE(SUM(bagage_inter_amount), 0) as bagage_inter_total,
                        COALESCE(SUM(bagage_national_amount), 0) as bagage_national_total,
                        COALESCE(SUM(amount), 0) as total_amount
                    '
            )
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->whereIn('gare_id', $gareIds)
            ->groupBy('gare_id')
            ->with('gare')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        $recentNotifications = NotificationHistory::query()
            ->where('user_id', $user->id)
            ->forModule($module)
            ->latest('created_at')
            ->limit(5)
            ->get();

        return [
            'mode' => 'financial',
            'module' => $module,
            'user_can_view_all' => $user->canViewAllGares(),
            'period_label' => $user->canViewAllGares()
                ? 'Periode filtree'
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
            'weekly_comparison' => $weeklyComparison,
            'top_recettes' => $topRecettes,
            'top_depenses' => $topDepenses,
            'top_saisie' => $topSaisie,
            'recette_breakdown_totals' => $recetteBreakdownTotals,
            'recette_breakdown_by_gare' => $recetteBreakdownByGare,
            'recent_notifications' => $recentNotifications,
            'show_global_sections' => $showGlobalSections,
            'is_courrier' => $isCourrier,
            'trend_chart' => $this->buildTrendChart($trend),
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
            ->forModule(ServiceModule::Documents)
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

    protected function buildTrendChart(Collection $trend): array
    {
        $rows = $trend->values();
        $max = max(1, (float) $rows
            ->flatMap(fn ($row) => [(float) ($row['recettes'] ?? 0), (float) ($row['depenses'] ?? 0), (float) ($row['versements'] ?? 0)])
            ->max());

        $pointBuilder = function (string $key) use ($rows, $max): string {
            $count = max(1, $rows->count() - 1);

            return $rows->map(function ($row, $index) use ($count, $key, $max) {
                $x = 20 + ($index * (300 / $count));
                $y = 140 - (((float) ($row[$key] ?? 0) / $max) * 110);

                return round($x, 2).','.round($y, 2);
            })->implode(' ');
        };

        return [
            'max' => $max,
            'recettes' => $pointBuilder('recettes'),
            'depenses' => $pointBuilder('depenses'),
            'versements' => $pointBuilder('versements'),
        ];
    }
}

