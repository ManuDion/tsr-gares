<?php

namespace App\Http\Controllers\Rh;

use App\Enums\ServiceModule;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Gare;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccessRhModule(), 403);

        $employees = Employee::query()
            ->with(['department', 'gare', 'user'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('rh.employees.index', compact('employees'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canAccessRhModule() && ! $request->user()->isPersonnelTsr(), 403);

        return view('rh.employees.create', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'gares' => Gare::query()->where('is_active', true)->orderBy('name')->get(),
            'rhUsers' => User::query()->whereIn('role', ['responsable_rh', 'personnel_tsr'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessRhModule() && ! $request->user()->isPersonnelTsr(), 403);

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:60', 'unique:employees,employee_code'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['required', 'string', 'max:40'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'gare_id' => ['nullable', 'exists:gares,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'mobile_app_enabled' => ['nullable', 'boolean'],
        ]);

        [$firstName, $lastName] = $this->splitName($data['full_name']);

        $employee = Employee::create([
            'employee_code' => $data['employee_code'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'employment_status' => $data['employment_status'],
            'department_id' => $data['department_id'] ?? Department::forModule(ServiceModule::Rh)?->id,
            'gare_id' => $data['gare_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'mobile_app_enabled' => $request->boolean('mobile_app_enabled'),
        ]);

        if ($employee->department_id || $employee->gare_id || $employee->job_title) {
            $employee->assignments()->create([
                'department_id' => $employee->department_id,
                'gare_id' => $employee->gare_id,
                'job_title' => $employee->job_title,
                'assigned_at' => $employee->hire_date ?: now()->toDateString(),
                'decision_reference' => 'Affectation initiale',
                'created_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('rh.employees.show', $employee)->with('status', 'Dossier RH créé.');
    }

    public function show(Request $request, Employee $employee): View
    {
        abort_unless($request->user()->canAccessRhModule(), 403);

        if ($request->user()->isPersonnelTsr()) {
            abort_unless($employee->user_id === $request->user()->id, 403);
        }

        return view('rh.employees.show', [
            'employee' => $employee->load(['department', 'gare', 'user', 'documents.uploader', 'assignments.department', 'assignments.gare']),
        ]);
    }

    public function edit(Request $request, Employee $employee): View
    {
        abort_unless($request->user()->canAccessRhModule() && ! $request->user()->isPersonnelTsr(), 403);

        return view('rh.employees.edit', [
            'employee' => $employee,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'gares' => Gare::query()->where('is_active', true)->orderBy('name')->get(),
            'rhUsers' => User::query()->whereIn('role', ['responsable_rh', 'personnel_tsr'])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->canAccessRhModule() && ! $request->user()->isPersonnelTsr(), 403);

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:60', 'unique:employees,employee_code,'.$employee->id],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['required', 'string', 'max:40'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'gare_id' => ['nullable', 'exists:gares,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'mobile_app_enabled' => ['nullable', 'boolean'],
        ]);

        [$firstName, $lastName] = $this->splitName($data['full_name']);

        $employee->update([
            'employee_code' => $data['employee_code'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'employment_status' => $data['employment_status'],
            'department_id' => $data['department_id'] ?? Department::forModule(ServiceModule::Rh)?->id,
            'gare_id' => $data['gare_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'mobile_app_enabled' => $request->boolean('mobile_app_enabled'),
        ]);

        return redirect()->route('rh.employees.show', $employee)->with('status', 'Dossier RH mis à jour.');
    }

    protected function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $first = $parts[0] ?? $fullName;
        array_shift($parts);

        return [$first, implode(' ', $parts)];
    }
}
