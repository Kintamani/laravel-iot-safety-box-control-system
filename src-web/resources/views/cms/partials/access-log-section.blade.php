@php
    $groupedLogs = $logs->getCollection()->groupBy(function ($log) {
        return $log->timestamp?->format('Y-m-d') ?? 'unknown';
    });
@endphp

<section id="access-log-section" class="mt-8 rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-mono uppercase tracking-[0.2em] text-slate-500">Activity Log</p>
            <h2 class="text-xl font-semibold text-slate-900">Riwayat Unlock/Lock</h2>
        </div>
        <form id="access-log-filter" method="GET" action="{{ route('cms.dashboard') }}"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.4fr_1fr_auto]">
            <input type="text" name="search" value="{{ $logFilters['search'] }}" placeholder="Cari nilai tabel log"
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-200" />
            <input type="date" name="filter_date" value="{{ $logFilters['filter_date'] }}"
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-200" />
            <div class="flex gap-2">
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    Terapkan
                </button>
                <a href="{{ route('cms.dashboard') }}"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">
                    Reset
                </a>
            </div>
        </form>
    </div>
    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase tracking-[0.15em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Box</th>
                    <th class="px-4 py-3">Log</th>
                    <th class="px-4 py-3">Order</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($groupedLogs as $date => $dateLogs)
                    <tr class="bg-slate-50">
                        <td colspan="4" class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            {{ $date !== 'unknown' ? \Carbon\Carbon::parse($date)->translatedFormat('d F Y') : 'Tanggal tidak tersedia' }}
                        </td>
                    </tr>
                    @foreach ($dateLogs as $log)
                        @php
                            $isUnlock = strtolower((string) $log->log_type) === 'unlock';
                        @endphp
                        <tr class="bg-white/60">
                            <td class="px-4 py-3 text-slate-600">{{ $log->timestamp?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $log->box_id }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <div>
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $isUnlock ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $log->log_type }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-slate-400">{{ $qrTypeLabels[$log->qrCode?->type] ?? 'Manual / N/A' }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $log->qrCode?->order_id ? '#' . $log->qrCode->order_id : '-' }}
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada aktivitas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->total() > 0)
        <div class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
            <p>
                Menampilkan {{ $logs->firstItem() }}-{{ $logs->lastItem() }} dari {{ $logs->total() }} log
            </p>
            <div class="flex items-center gap-2">
                @if ($logs->onFirstPage())
                    <span class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-2 text-slate-400">Sebelumnya</span>
                @else
                    <a href="{{ $logs->previousPageUrl() }}"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700">
                        Sebelumnya
                    </a>
                @endif

                <span class="rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white">
                    Halaman {{ $logs->currentPage() }} / {{ $logs->lastPage() }}
                </span>

                @if ($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700">
                        Berikutnya
                    </a>
                @else
                    <span class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-2 text-slate-400">Berikutnya</span>
                @endif
            </div>
        </div>
    @endif
</section>
