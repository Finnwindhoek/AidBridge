{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
{{--
    Shown in place of an empty table or list.

    @param string      $icon     Bootstrap Icons name, without the "bi-" prefix
    @param string      $title
    @param string|null $message
    @param slot        $action   optional call-to-action button
--}}
@props(['icon' => 'inbox', 'title', 'message' => null])

<div class="empty-state">
    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    <p class="fw-semibold mb-1">{{ $title }}</p>
    @if ($message)
        <p class="small mb-0">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-3">{{ $action }}</div>
    @endisset
</div>
