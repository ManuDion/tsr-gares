@extends('layouts.app')

@section('title', 'Nouvelles dépenses')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Saisir jusqu’à 5 dépenses courrier' : 'Saisir jusqu’à 5 dépenses')
@section('subheading', 'Ajoutez plusieurs dépenses à la suite puis enregistrez-les en une seule opération.')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('depenses.store', ['module' => $module->value]) }}" enctype="multipart/form-data" class="stack-md" data-depense-repeater data-max-items="5" data-next-index="{{ collect($initialEntries)->keys()->max() !== null ? (collect($initialEntries)->keys()->max() + 1) : count($initialEntries) }}">
            @csrf
            <input type="hidden" name="module" value="{{ $module->value }}">

            <div id="depense-entries" class="stack-md">
                @foreach($initialEntries as $index => $entry)
                    @include('depenses.partials.entry', ['index' => $index, 'entry' => $entry, 'gares' => $gares, 'maxSizeKb' => $maxSizeKb, 'module' => $module, 'virtualGare' => $virtualGare ?? null])
                @endforeach
            </div>

            <div class="form-actions">
                <button class="btn btn-outline" type="button" data-add-depense>
                    <span class="icon">{!! app_icon('plus') !!}</span>
                    Ajouter une dépense
                </button>
            </div>

            <template id="depense-entry-template">
                @include('depenses.partials.entry', ['index' => '__INDEX__', 'entry' => null, 'gares' => $gares, 'maxSizeKb' => $maxSizeKb, 'module' => $module, 'virtualGare' => $virtualGare ?? null])
            </template>

            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('depenses.index', ['module' => $module->value]) }}">Annuler</a>
                <button class="btn btn-primary" type="submit"><span class="icon">{!! app_icon('plus') !!}</span> Enregistrer les dépenses</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-depense-repeater]').forEach(function (repeater) {
            const wrapper = repeater.querySelector('#depense-entries');
            const addButton = repeater.querySelector('[data-add-depense]');
            const template = repeater.querySelector('#depense-entry-template');
            const maxItems = parseInt(repeater.getAttribute('data-max-items') || '5', 10);

            function sanitizeIntegerField(input) {
                if (!input) return;
                const raw = String(input.value || '');
                const digits = raw.replace(/[^\d]/g, '');
                if (digits === '') {
                    input.value = '';
                    return;
                }
                input.value = String(parseInt(digits, 10));
            }

            function propagateFirstGareToEntries() {
                const entries = Array.from(wrapper.querySelectorAll('[data-depense-entry]'));
                if (entries.length <= 1) return;

                const firstEntry = entries[0];
                const firstLabel = firstEntry.querySelector('input[data-gare-label]');
                const firstHidden = firstEntry.querySelector('input[data-gare-id]');

                if (!firstLabel || !firstHidden || !firstHidden.value) {
                    return;
                }

                entries.slice(1).forEach(function (entry) {
                    const gareLabel = entry.querySelector('input[data-gare-label]');
                    const gareHidden = entry.querySelector('input[data-gare-id]');

                    if (!gareLabel || !gareHidden) return;
                    gareLabel.value = firstLabel.value;
                    gareHidden.value = firstHidden.value;
                });
            }

            function bindFirstGarePropagation() {
                function shouldPropagateFromTarget(target) {
                    if (!target || !target.matches('input[data-gare-label], input[data-gare-id]')) return false;
                    const entry = target.closest('[data-depense-entry]');
                    if (!entry) return false;
                    const firstEntry = wrapper.querySelector('[data-depense-entry]');

                    return entry === firstEntry;
                }

                wrapper.addEventListener('change', function (event) {
                    if (!shouldPropagateFromTarget(event.target)) return;
                    window.setTimeout(propagateFirstGareToEntries, 0);
                });

                wrapper.addEventListener('input', function (event) {
                    if (!shouldPropagateFromTarget(event.target)) return;
                    window.setTimeout(propagateFirstGareToEntries, 0);
                });
            }

            function refreshState() {
                const items = wrapper.querySelectorAll('[data-depense-entry]');
                items.forEach(function (item, idx) {
                    const removeBtn = item.querySelector('[data-remove-depense]');
                    if (removeBtn) {
                        removeBtn.hidden = items.length === 1;
                    }

                    const title = item.querySelector('[data-entry-title]');
                    if (title) {
                        title.textContent = 'Dépense ' + (idx + 1);
                    }
                });

                addButton.disabled = items.length >= maxItems;
            }

            addButton.addEventListener('click', function () {
                const currentCount = wrapper.querySelectorAll('[data-depense-entry]').length;
                if (currentCount >= maxItems) return;

                const nextIndex = parseInt(repeater.getAttribute('data-next-index') || currentCount, 10);
                const html = template.innerHTML.replace(/__INDEX__/g, nextIndex);
                wrapper.insertAdjacentHTML('beforeend', html);
                repeater.setAttribute('data-next-index', String(nextIndex + 1));
                propagateFirstGareToEntries();
                refreshState();
            });

            wrapper.addEventListener('click', function (event) {
                const button = event.target.closest('[data-remove-depense]');
                if (! button) return;

                const entry = button.closest('[data-depense-entry]');
                if (entry) {
                    entry.remove();
                    propagateFirstGareToEntries();
                    refreshState();
                }
            });

            wrapper.addEventListener('input', function (event) {
                const input = event.target.closest('[data-depense-entry-amount]');
                if (!input) return;
                sanitizeIntegerField(input);
            });

            wrapper.addEventListener('focusin', function (event) {
                const input = event.target.closest('[data-depense-entry-amount]');
                if (!input) return;
                if ((input.value || '').trim() === '0') {
                    input.value = '';
                }
            });

            wrapper.addEventListener('focusout', function (event) {
                const input = event.target.closest('[data-depense-entry-amount]');
                if (!input) return;
                sanitizeIntegerField(input);
            });

            bindFirstGarePropagation();
            propagateFirstGareToEntries();
            refreshState();
        });
    </script>
@endpush
