@extends('layouts.app')

@section('title', 'Conversation')
@section('heading', $conversation->displayNameFor(auth()->user()))
@section('subheading', $conversation->typeLabel())

@section('actions')
    <a class="btn btn-outline" href="{{ route('chat.index', ['module' => request('module')]) }}">
        <span class="icon">{!! app_icon('back') !!}</span> Retour au chat
    </a>
@endsection

@section('content')
    <div class="chat-thread">
        <div class="panel">
            <h2>Participants</h2>
            <p>{{ $conversation->participants->pluck('name')->implode(', ') }}</p>
        </div>

        <div class="panel">
            <h2>Messages</h2>
            <div class="list-stack">
                @forelse($conversation->messages as $message)
                    <div class="chat-bubble {{ $message->user_id === auth()->id() ? 'me' : '' }}">
                        <div class="chat-meta">
                            <strong>{{ $message->user?->name ?? 'Utilisateur' }}</strong>
                            <span>{{ $message->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        @if(filled($message->content))
                            <div>{{ $message->content }}</div>
                        @endif
                        @if($message->isAudio())
                            <div class="mt-sm">
                                <audio controls preload="none" class="media-control-full">
                                    <source src="{{ route('chat.messages.audio', $message) }}" type="{{ $message->audio_mime_type ?: 'audio/webm' }}">
                                    Votre navigateur ne supporte pas la lecture audio.
                                </audio>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>Aucun message</strong>
                        <p>Envoyez le premier message a partir du formulaire ci-dessous.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h2>Nouveau message</h2>
            <form method="POST" action="{{ route('chat.messages.store', ['conversation' => $conversation, 'module' => request('module')]) }}" class="stack-md" enctype="multipart/form-data" data-audio-chat-form>
                @csrf
                <div>
                    <label>Message</label>
                    <textarea class="message-box" name="content" rows="5" placeholder="Tapez votre message (optionnel si vous envoyez un audio)"></textarea>
                </div>
                <div class="stack-sm">
                    <label>Message audio</label>
                    <input type="file" name="audio" accept="audio/*" data-audio-file-input hidden>
                    <div class="doc-links doc-links-end">
                        <button type="button" class="btn btn-sm btn-outline" data-audio-record-toggle aria-pressed="false" title="Microphone: clic pour demarrer, clic pour arreter">
                            <span class="icon">{!! app_icon('microphone') !!}</span>
                            <span data-audio-toggle-label>Micro</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" data-audio-record-clear hidden>Effacer</button>
                    </div>
                    <small data-audio-record-status>Aucun enregistrement en cours.</small>
                    <audio controls hidden data-audio-preview class="media-control-full"></audio>
                </div>
                <button class="btn btn-primary" type="submit">
                    <span class="icon">{!! app_icon('chat') !!}</span> Envoyer
                </button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-audio-chat-form]').forEach(function(form) {
            const toggleBtn = form.querySelector('[data-audio-record-toggle]');
            const toggleLabel = form.querySelector('[data-audio-toggle-label]');
            const clearBtn = form.querySelector('[data-audio-record-clear]');
            const statusText = form.querySelector('[data-audio-record-status]');
            const fileInput = form.querySelector('[data-audio-file-input]');
            const preview = form.querySelector('[data-audio-preview]');
            if (!toggleBtn || !toggleLabel || !clearBtn || !statusText || !fileInput || !preview) return;

            let mediaRecorder = null;
            let mediaStream = null;
            let chunks = [];
            let blobUrl = null;
            let preferredMimeType = 'audio/webm';
            let isRecording = false;

            function setStatus(text) {
                statusText.textContent = text;
            }

            function setToggleState(recording) {
                isRecording = recording;
                toggleBtn.setAttribute('aria-pressed', recording ? 'true' : 'false');
                toggleLabel.textContent = recording ? 'Arreter' : 'Micro';
                toggleBtn.classList.toggle('btn-primary', recording);
                toggleBtn.classList.toggle('btn-outline', !recording);
            }

            function stopTracks() {
                if (mediaStream) {
                    mediaStream.getTracks().forEach(function(track) { track.stop(); });
                    mediaStream = null;
                }
            }

            function clearPreview() {
                if (blobUrl) {
                    URL.revokeObjectURL(blobUrl);
                    blobUrl = null;
                }
                preview.hidden = true;
                preview.src = '';
                fileInput.value = '';
                clearBtn.hidden = true;
            }

            async function startRecording() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
                    setStatus("L'enregistrement audio n'est pas supporte sur ce navigateur.");
                    return;
                }

                clearPreview();
                chunks = [];

                try {
                    mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    const options = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                        ? { mimeType: 'audio/webm;codecs=opus' }
                        : {};
                    preferredMimeType = options.mimeType ? 'audio/webm' : 'audio/ogg';
                    mediaRecorder = new MediaRecorder(mediaStream, options);

                    mediaRecorder.addEventListener('dataavailable', function(event) {
                        if (event.data && event.data.size > 0) {
                            chunks.push(event.data);
                        }
                    });

                    mediaRecorder.addEventListener('stop', function() {
                        stopTracks();
                        if (!chunks.length) {
                            setStatus('Enregistrement vide.');
                            return;
                        }

                        const blob = new Blob(chunks, { type: preferredMimeType });
                        blobUrl = URL.createObjectURL(blob);
                        preview.src = blobUrl;
                        preview.hidden = false;
                        clearBtn.hidden = false;

                        const extension = preferredMimeType === 'audio/ogg' ? 'ogg' : 'webm';
                        const file = new File([blob], 'message-audio.' + extension, { type: preferredMimeType });
                        const transfer = new DataTransfer();
                        transfer.items.add(file);
                        fileInput.files = transfer.files;
                        setStatus('Audio pret a etre envoye.');
                        setToggleState(false);
                    });

                    mediaRecorder.start();
                    setToggleState(true);
                    setStatus('Enregistrement en cours...');
                } catch (error) {
                    stopTracks();
                    setToggleState(false);
                    setStatus("Impossible d'acceder au microphone.");
                }
            }

            function stopRecording() {
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    mediaRecorder.stop();
                    setToggleState(false);
                }
            }

            toggleBtn.addEventListener('click', function() {
                if (isRecording) {
                    stopRecording();
                } else {
                    startRecording();
                }
            });

            clearBtn.addEventListener('click', function() {
                clearPreview();
                setToggleState(false);
                setStatus('Audio efface.');
            });

            setToggleState(false);
        });
    </script>
@endpush
