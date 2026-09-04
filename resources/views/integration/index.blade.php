{{--
    AidBridge — Welfare Aid & Cash Assistance Distribution Management System

    Shared component — not owned by a single module.
    Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--}}
@extends('layouts.app')

@section('title', 'Module Integration')

@section('content')
    <x-page-header
        title="Module integration"
        subtitle="Live module-to-module web service calls. Every module both exposes a service and consumes a sibling's.">
        <x-slot:actions>
            <a href="{{ route('integration.index') }}" class="btn btn-aidbridge btn-sm">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Re-run all calls
            </a>
        </x-slot:actions>
    </x-page-header>

    @php
        $ok = collect($results)->where('ok', true)->count();
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <x-stat-card label="Calls made" :value="count($results)" icon="diagram-3" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card label="Succeeded" :value="$ok" icon="check-circle"
                :meta="$ok === count($results) ? 'All modules reachable' : 'Some calls degraded'" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card label="Subject application" :value="$subject?->reference ? Str::limit($subject->reference, 13, '…') : '—'"
                icon="file-earmark-text" meta="Used by the two per-application calls" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card label="Protocol" value="REST / JSON" icon="hdd-network"
                meta="Bearer token, IFA envelope" />
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-diagram-3" aria-hidden="true"></i> Call results</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <caption class="visually-hidden">Module-to-module web service call results</caption>
                <thead>
                    <tr>
                        <th scope="col">Consumer</th>
                        <th scope="col">Provider</th>
                        <th scope="col">Function</th>
                        <th scope="col">Status</th>
                        <th scope="col">HTTP</th>
                        <th scope="col" class="text-end">Time</th>
                        <th scope="col">requestID</th>
                        <th scope="col">timeStamp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results as $r)
                        <tr>
                            <td>{{ $r->sourceModule }}</td>
                            <td>{{ $r->targetModule }}</td>
                            <td><code>{{ $r->function }}</code></td>
                            <td>
                                @if ($r->ok)
                                    <span class="badge text-bg-success">
                                        <i class="bi bi-check-circle" aria-hidden="true"></i> {{ $r->status }}
                                    </span>
                                @else
                                    <span class="badge text-bg-danger">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i> {{ $r->status }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $r->httpStatus ?? '—' }}</td>
                            <td class="text-end">{{ $r->durationMs !== null ? $r->durationMs.' ms' : '—' }}</td>
                            <td><code class="small">{{ Str::limit($r->requestId, 8, '') }}</code></td>
                            <td class="small">{{ $r->timeStamp ?? '—' }}</td>
                        </tr>
                        @unless ($r->ok)
                            <tr>
                                <td colspan="8" class="small text-danger ps-4">
                                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                    {{ $r->error }}
                                </td>
                            </tr>
                        @endunless
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($results as $r)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><code>{{ $r->function }}</code></span>
                <span class="small text-muted">{{ $r->url }}</span>
            </div>
            <div class="card-body">
                @if ($r->ok)
                    <pre class="small mb-0" style="max-height: 260px; overflow: auto;">{{ json_encode($r->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <p class="text-danger mb-0">{{ $r->error }}</p>
                @endif
            </div>
        </div>
    @endforeach
@endsection
