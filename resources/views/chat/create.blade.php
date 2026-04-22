@extends('layouts.app')

@section('title', 'Nouvelle conversation')
@section('heading', 'Nouvelle conversation')
@section('subheading', 'General, interne service, ou direct entre deux personnes')

@section('actions')
    <a class="btn btn-outline" href="{{ route('chat.index', ['module' => request('module')]) }}">
        <span class="icon">{!! app_icon('back') !!}</span> Retour au chat
    </a>
@endsection

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('chat.store', ['module' => request('module')]) }}" class="stack-md">
            @csrf

            <div>
                <label>Type de conversation</label>
                <select name="conversation_type" data-conversation-type>
                    @foreach($conversationTypeOptions as $option)
                        <option value="{{ $option['value'] }}" @selected(old('conversation_type', 'direct') === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <small>
                    General = tout le personnel. Interne service = membres d'un meme service. Direct = echange B to B.
                </small>
            </div>

            <div data-service-section>
                <label>Service cible</label>
                <select name="service_module">
                    <option value="">Selectionner un service</option>
                    @foreach($serviceOptions as $option)
                        <option value="{{ $option['value'] }}" @selected(old('service_module') === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <small>Le canal inclura uniquement les membres de ce service.</small>
            </div>

            <div data-direct-section>
                <label>Interlocuteur</label>
                <div data-user-autocomplete>
                    <input type="text" name="direct_user_label" list="chat-direct-users" data-user-label value="{{ old('direct_user_label') }}" placeholder="Tapez un nom (ex: y...)">
                    <input type="hidden" name="direct_user_id" data-user-id value="{{ old('direct_user_id') }}">
                    <datalist id="chat-direct-users">
                        @foreach($directUsers as $item)
                            <option value="{{ $item->name }}" data-id="{{ $item->id }}">{{ $item->roleLabel() }}</option>
                        @endforeach
                    </datalist>
                </div>
                <small>En saisissant une lettre, la liste des noms filtrera automatiquement.</small>
            </div>

            <div>
                <label>Premier message (optionnel)</label>
                <textarea name="initial_message" rows="4">{{ old('initial_message') }}</textarea>
            </div>

            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('chat.index', ['module' => request('module')]) }}">Annuler</a>
                <button class="btn btn-primary" type="submit">
                    <span class="icon">{!! app_icon('chat') !!}</span>
                    Ouvrir la conversation
                </button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const typeSelect = document.querySelector('[data-conversation-type]');
            const serviceSection = document.querySelector('[data-service-section]');
            const directSection = document.querySelector('[data-direct-section]');
            const userAutocomplete = document.querySelector('[data-user-autocomplete]');

            function syncTypeSections() {
                if (!typeSelect) return;
                const type = typeSelect.value;
                if (serviceSection) {
                    serviceSection.hidden = type !== 'service_internal';
                }
                if (directSection) {
                    directSection.hidden = type !== 'direct';
                }
            }

            function syncDirectUser() {
                if (!userAutocomplete) return;
                const textInput = userAutocomplete.querySelector('input[data-user-label]');
                const hiddenInput = userAutocomplete.querySelector('input[data-user-id]');
                const options = Array.from(userAutocomplete.querySelectorAll('datalist option'));
                if (!textInput || !hiddenInput || !options.length) return;

                const inputValue = textInput.value.toLowerCase();
                const exact = options.find((option) => option.value === textInput.value);
                const partial = options.find((option) => option.value.toLowerCase().includes(inputValue));
                const match = exact || partial;
                hiddenInput.value = match ? match.dataset.id : '';
            }

            if (typeSelect) {
                typeSelect.addEventListener('change', syncTypeSections);
                syncTypeSections();
            }

            if (userAutocomplete) {
                const textInput = userAutocomplete.querySelector('input[data-user-label]');
                if (textInput) {
                    textInput.addEventListener('input', syncDirectUser);
                    textInput.addEventListener('change', syncDirectUser);
                    syncDirectUser();
                }
            }
        })();
    </script>
@endsection
