@extends('layouts.dashboard')
@section('title', 'Memory — Butler')

@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Butler Memory <i class="fas fa-brain" style="font-size:18px;color:var(--accent)"></i></h2>
        <p>Pola yang sudah Butler pelajari dari kebiasaan kamu. Hapus jika tidak sesuai.</p>
    </div>
</div>

@if($memories->isEmpty())
<div class="empty-state animate-in">
    <div class="empty-icon"><i class="fas fa-brain"></i></div>
    <h3>Belum ada yang dipelajari</h3>
    <p>Semakin sering kamu pakai Butler, semakin banyak pola yang akan muncul di sini.</p>
</div>
@else

@foreach($memories as $domain => $rows)
<div class="card animate-in" style="padding:0;overflow:hidden;margin-bottom:16px">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
        <span style="font-size:16px">{{ $domainLabels[$domain] ?? $domain }}</span>
        <span style="font-size:11px;color:var(--text-dim);background:var(--bg-hover);
                     padding:2px 8px;border-radius:999px;margin-left:auto">
            {{ $rows->count() }} pola
        </span>
    </div>

    <div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Subjek</th>
                <th>Nilai Dipelajari</th>
                <th style="text-align:center">Kepercayaan</th>
                <th style="text-align:center">Observasi</th>
                <th style="text-align:center">Auto</th>
                <th style="text-align:center">Terakhir Dilihat</th>
                <th style="text-align:center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $mem)
            @php
                $pct  = (int) round($mem->behavioral_confidence * 100);
                $color = $pct >= 80 ? 'var(--green)' : ($pct >= 50 ? 'var(--orange)' : 'var(--red)');
                $val   = $mem->value ?? [];
            @endphp
            <tr id="mem-row-{{ $mem->id }}">
                <td style="font-weight:600;font-family:monospace;color:var(--text-primary)">
                    {{ $mem->subject }}
                </td>
                <td style="font-size:12px;color:var(--text-secondary)">
                    @if(isset($val['account_name']))
                        → {{ $val['account_name'] }}
                    @elseif(isset($val['calories']))
                        {{ $val['calories'] }} kcal
                        @if(isset($val['source']))
                            <span style="font-size:10px;color:var(--text-dim)">({{ $val['source'] }})</span>
                        @endif
                    @else
                        <span style="color:var(--text-dim)">{{ json_encode($val, JSON_UNESCAPED_UNICODE) }}</span>
                    @endif
                </td>
                <td style="text-align:center">
                    <div style="display:inline-flex;flex-direction:column;align-items:center;gap:3px;min-width:64px">
                        <span style="font-weight:700;font-size:13px;color:{{ $color }}">{{ $pct }}%</span>
                        <div style="width:60px;height:4px;background:var(--bg-elevated);border-radius:2px;overflow:hidden">
                            <div style="width:{{ $pct }}%;height:100%;background:{{ $color }};border-radius:2px"></div>
                        </div>
                    </div>
                </td>
                <td style="text-align:center;color:var(--text-muted);font-size:13px">
                    {{ $mem->observation_count }}×
                </td>
                <td style="text-align:center">
                    @if($mem->auto_apply)
                        <span style="color:var(--green);font-size:13px"><i class="fas fa-check" style="font-size:10px"></i> Auto</span>
                    @elseif($mem->behavioral_confidence >= 0.8)
                        <span style="color:var(--orange);font-size:11px"><i class="fas fa-clock" style="font-size:10px"></i> Pending consent</span>
                    @else
                        <span style="color:var(--text-dim);font-size:11px">Sugesti saja</span>
                    @endif
                </td>
                <td style="text-align:center;color:var(--text-dim);font-size:12px;white-space:nowrap">
                    {{ $mem->last_observed_at?->diffForHumans() ?? '—' }}
                </td>
                <td style="text-align:center">
                    <button onclick="deleteMemory({{ $mem->id }})"
                        style="background:none;border:1px solid var(--border);color:var(--red);
                               padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;
                               transition:background .15s"
                        onmouseover="this.style.background='rgba(239,68,68,0.08)'"
                        onmouseout="this.style.background='none'">
                        Hapus
                    </button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endforeach

@endif

<div class="card animate-in" style="background:rgba(139,92,246,0.04);border-color:rgba(139,92,246,0.15);margin-top:4px">
    <div style="display:flex;gap:12px;align-items:flex-start">
        <i class="fas fa-lightbulb" style="font-size:18px;color:var(--yellow);flex-shrink:0;margin-top:2px"></i>
        <div style="font-size:13px;color:var(--text-secondary);line-height:1.6">
            <strong style="color:var(--text-primary)">Bagaimana Memory bekerja?</strong><br>
            Butler mempelajari kebiasaanmu dari setiap transaksi yang dikonfirmasi.
            Setelah cukup banyak observasi (kepercayaan ≥ 50%), Butler mulai menyarankan pola tersebut.
            Di atas 80%, Butler akan meminta izin untuk mengotomatiskan.
            Di 100%, Butler langsung menerapkan tanpa konfirmasi.<br>
            <span style="color:var(--text-dim);font-size:11px;margin-top:4px;display:block">
                Menghapus satu baris tidak menghapus riwayat transaksi — hanya pola yang dipelajarinya.
            </span>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

async function deleteMemory(id) {
    if (!confirm('Hapus pola ini? Butler tidak akan lagi mengingatnya.')) return;

    const row = document.getElementById('mem-row-' + id);
    if (row) row.style.opacity = '0.4';

    try {
        const res = await fetch(`/dashboard/memory/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        if (res.ok) {
            if (row) row.remove();
        } else {
            alert('Gagal menghapus. Coba lagi.');
            if (row) row.style.opacity = '1';
        }
    } catch (e) {
        alert('Network error. Coba lagi.');
        if (row) row.style.opacity = '1';
    }
}
</script>

@endsection
