{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
{{--
    Renders any status enum that exposes label(), colour() and icon().

    The icon matters for accessibility: colour alone is not a reliable signal
    for colour-blind users, so every badge carries a shape as well.

    @param \BackedEnum $status
--}}
@props(['status'])

<span class="badge text-bg-{{ $status->colour() }}">
    <i class="bi bi-{{ $status->icon() }}" aria-hidden="true"></i>
    {{ $status->label() }}
</span>
