{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Module 2 — Application & Document Management
    Author: Lee Kar How
--}}
{{--
    Help assistant.

    A floating panel offered to beneficiaries. Answers come from the JSON
    endpoint; nothing is rendered with innerHTML, so a reply can never inject
    markup even though every reply is server-composed plain text anyway.
--}}
@props(['starters' => []])

<div class="assistant no-print">
    <button type="button" id="assistantToggle" class="assistant-launcher"
            aria-expanded="false" aria-controls="assistantPanel">
        <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
        <span class="visually-hidden">Open the help assistant</span>
    </button>

    <div class="assistant-panel card" id="assistantPanel" hidden>
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-chat-dots" aria-hidden="true"></i> AidBridge Assistant</span>
            <button type="button" id="assistantClose" class="btn-close btn-sm" aria-label="Close assistant"></button>
        </div>

        {{-- aria-live so a screen reader announces each reply as it arrives. --}}
        <div class="assistant-log" id="assistantLog" role="log" aria-live="polite" aria-atomic="false">
            <div class="assistant-msg assistant-msg-bot">
                Hi {{ Str::before(auth()->user()->name, ' ') }} — ask me about your application,
                your payment, or the documents you need.
            </div>
        </div>

        <div class="assistant-suggestions" id="assistantSuggestions">
            @foreach ($starters as $starter)
                <button type="button" class="btn btn-sm btn-outline-secondary assistant-chip">{{ $starter }}</button>
            @endforeach
        </div>

        <form class="assistant-form" id="assistantForm">
            <label for="assistantInput" class="visually-hidden">Your question</label>
            <input type="text" id="assistantInput" class="form-control form-control-sm"
                   placeholder="Type a question…" maxlength="300" autocomplete="off" required>
            <button type="submit" class="btn btn-sm btn-aidbridge" id="assistantSend">
                <i class="bi bi-send" aria-hidden="true"></i>
                <span class="visually-hidden">Send</span>
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const toggle      = document.getElementById('assistantToggle');
    const panel       = document.getElementById('assistantPanel');
    const closeBtn    = document.getElementById('assistantClose');
    const form        = document.getElementById('assistantForm');
    const input       = document.getElementById('assistantInput');
    const send        = document.getElementById('assistantSend');
    const log         = document.getElementById('assistantLog');
    const suggestions = document.getElementById('assistantSuggestions');

    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function setOpen(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) input.focus();
    }

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    closeBtn.addEventListener('click', () => { setOpen(false); toggle.focus(); });

    // Escape closes the panel, as it would any other dismissible overlay.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) { setOpen(false); toggle.focus(); }
    });

    // textContent, never innerHTML — a reply is data, not markup.
    function addMessage(text, who) {
        const el = document.createElement('div');
        el.className = 'assistant-msg assistant-msg-' + who;
        el.textContent = text;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
        return el;
    }

    function renderSuggestions(items) {
        suggestions.replaceChildren();
        (items || []).forEach((text) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'btn btn-sm btn-outline-secondary assistant-chip';
            chip.textContent = text;
            suggestions.appendChild(chip);
        });
    }

    function addLink(link) {
        if (!link) return;
        const a = document.createElement('a');
        a.href = link.url;
        a.className = 'assistant-link';
        a.textContent = link.label + ' →';
        log.appendChild(a);
        log.scrollTop = log.scrollHeight;
    }

    async function ask(question) {
        addMessage(question, 'user');
        suggestions.replaceChildren();
        input.value = '';
        send.disabled = true;

        const thinking = addMessage('…', 'bot');

        try {
            const response = await fetch('{{ route('assistant.ask') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ question }),
            });

            if (!response.ok) throw new Error('Request failed');

            const reply = await response.json();
            thinking.textContent = reply.message;
            addLink(reply.link);
            renderSuggestions(reply.suggestions);
        } catch (e) {
            thinking.textContent = 'Sorry, I could not reach the server. Please try again in a moment.';
        } finally {
            send.disabled = false;
            input.focus();
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const question = input.value.trim();
        if (question) ask(question);
    });

    // One delegated listener, so chips replaced after every reply still work.
    suggestions.addEventListener('click', (e) => {
        const chip = e.target.closest('.assistant-chip');
        if (chip) ask(chip.textContent);
    });
})();
</script>
@endpush
