<div class="form-grid">
    <div>
        <label>Code</label>
        <input type="text" name="code" value="{{ old('code', $gare->code ?? '') }}" required>
    </div>
    <div>
        <label>Nom</label>
        <input type="text" name="name" value="{{ old('name', $gare->name ?? '') }}" required>
    </div>
    <div>
        <label>Ville</label>
        <input type="text" name="city" value="{{ old('city', $gare->city ?? '') }}" required>
    </div>
    <div>
        <label>Zone</label>
        <input type="text" name="zone" value="{{ old('zone', $gare->zone ?? '') }}">
    </div>
    <div class="col-span-2">
        <label>Adresse</label>
        <input type="text" name="address" value="{{ old('address', $gare->address ?? '') }}">
    </div>
    <div>
        <label>Mode de versement</label>
        <select name="versement_mode" data-versement-mode>
            @php $versementMode = old('versement_mode', $gare->versement_mode ?? 'direct'); @endphp
            <option value="direct" @selected($versementMode === 'direct')>Versement direct par la gare</option>
            <option value="cashier" @selected($versementMode === 'cashier')>Versement confie au caissier</option>
        </select>
    </div>
    <div>
        <label>Type d'activite</label>
        @php $activityMode = old('activity_mode', $gare->activity_mode ?? 'mixed'); @endphp
        <select name="activity_mode">
            <option value="mixed" @selected($activityMode === 'mixed')>Inter et national</option>
            <option value="inter_only" @selected($activityMode === 'inter_only')>Inter uniquement</option>
            <option value="national_only" @selected($activityMode === 'national_only')>National uniquement</option>
        </select>
        <small>Inter uniquement: les champs nationaux sont bloques. National uniquement: les champs inter sont bloques.</small>
    </div>
    <div data-cashier-field>
        <label>Caissier responsable</label>
        <select name="cashier_user_id">
            <option value="">Selectionner un caissier</option>
            @foreach(($cashiers ?? collect()) as $cashier)
                <option value="{{ $cashier->id }}" @selected((string) old('cashier_user_id', $gare->cashier_user_id ?? '') === (string) $cashier->id)>{{ $cashier->name }} ({{ $cashier->roleLabel() }})</option>
            @endforeach
        </select>
        <small>Obligatoire si le versement est confie a un caissier.</small>
    </div>
    <div>
        <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $gare->is_active ?? true))>
            <span>Gare active</span>
        </label>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-versement-mode]').forEach(function (select) {
                const wrapper = select.closest('.form-grid');
                const cashierField = wrapper ? wrapper.querySelector('[data-cashier-field]') : null;

                function syncCashierField() {
                    if (! cashierField) return;
                    cashierField.hidden = select.value !== 'cashier';
                }

                select.addEventListener('change', syncCashierField);
                syncCashierField();
            });
        </script>
    @endpush
@endonce
