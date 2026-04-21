<div class="form-grid">
    <div>
        <label>Code agent</label>
        <input type="text" name="employee_code" value="{{ old('employee_code', $employee->employee_code ?? '') }}" required>
    </div>
    <div>
        <label>Nom complet</label>
        <input type="text" name="full_name" value="{{ old('full_name', $employee->full_name ?? '') }}" required>
    </div>
    <div>
        <label>Téléphone</label>
        <input type="text" name="phone" value="{{ old('phone', $employee->phone ?? '') }}">
    </div>
    <div>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $employee->email ?? '') }}">
    </div>
    <div>
        <label>Fonction</label>
        <input type="text" name="job_title" value="{{ old('job_title', $employee->job_title ?? '') }}">
    </div>
    <div>
        <label>Date d'embauche / affectation</label>
        <input type="date" name="hire_date" value="{{ old('hire_date', optional($employee->hire_date ?? null)->format('Y-m-d')) }}">
    </div>
    <div>
        <label>Statut</label>
        <select name="employment_status" required>
            @php($status = old('employment_status', $employee->employment_status ?? 'active'))
            @foreach(['active' => 'Actif', 'draft' => 'Brouillon', 'suspended' => 'Suspendu', 'left' => 'Sorti'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Service</label>
        <select name="department_id">
            <option value="">Sélectionner un service</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((string) old('department_id', $employee->department_id ?? '') === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Gare / site</label>
        <select name="gare_id">
            <option value="">Aucune gare</option>
            @foreach($gares as $gare)
                <option value="{{ $gare->id }}" @selected((string) old('gare_id', $employee->gare_id ?? '') === (string) $gare->id)>{{ $gare->name }} — {{ $gare->city }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Compte utilisateur lié</label>
        <select name="user_id">
            <option value="">Aucun compte lié</option>
            @foreach($rhUsers as $rhUser)
                <option value="{{ $rhUser->id }}" @selected((string) old('user_id', $employee->user_id ?? '') === (string) $rhUser->id)>{{ $rhUser->name }} — {{ $rhUser->email }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-2">
        <label class="checkbox-line">
            <input type="checkbox" name="mobile_app_enabled" value="1" @checked(old('mobile_app_enabled', $employee->mobile_app_enabled ?? false))>
            <span>Application mobile du personnel autorisée</span>
        </label>
    </div>
</div>
