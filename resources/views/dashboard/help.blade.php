@extends('layouts.dashboard')
@section('title', 'Panduan — Butler')

@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Apa aja yang bisa Butler lakuin? <i class="fas fa-wand-magic-sparkles" style="font-size:18px;color:var(--accent)"></i></h2>
        <p>Nggak perlu hafal perintah — ketik aja pesan biasa di Telegram, Butler ngerti sendiri. Tap contoh untuk menyalin.</p>
    </div>
</div>

{{-- Quick intro callout --}}
<div class="card animate-in" style="display:flex;gap:14px;align-items:flex-start">
    <span class="stat-icon" style="background:var(--grad-purple);width:42px;height:42px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0">
        <i class="fas fa-comment-dots"></i>
    </span>
    <div>
        <div style="font-weight:600;color:var(--text-primary);margin-bottom:2px">Ngobrol biasa aja</div>
        <div style="font-size:13px;color:var(--text-muted)">
            Salah ketik atau pakai kata lain? Butler tetap berusaha nebak dan nanya
            <em>“ini yang kamu maksud?”</em> kalau ragu. Daftar di bawah cuma contoh — variasinya bebas.
        </div>
    </div>
</div>

@foreach($groups as $group)
<div class="card animate-in" style="padding:0;overflow:hidden;margin-bottom:16px">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
        <i class="fas {{ $group['icon'] }}" style="color:var(--accent);width:18px;text-align:center"></i>
        <span style="font-size:14px;font-weight:600;color:var(--text-primary)">{{ $group['label'] }}</span>
        <span style="font-size:11px;color:var(--text-dim);background:var(--bg-hover);
                     padding:2px 8px;border-radius:999px;margin-left:auto">
            {{ count($group['items']) }} fitur
        </span>
    </div>

    @foreach($group['items'] as $item)
    <div style="padding:14px 18px;{{ !$loop->last ? 'border-bottom:1px solid var(--border)' : '' }}">
        <div style="font-weight:600;color:var(--text-primary);font-size:14px;margin-bottom:3px">{{ $item['label'] }}</div>
        <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:8px">{{ $item['desc'] }}</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
            @foreach($item['examples'] as $ex)
            <button type="button" class="help-chip" data-copy="{{ $ex }}">
                <i class="fas fa-quote-left" style="font-size:8px;opacity:.5"></i> {{ $ex }}
            </button>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endforeach

<div class="card animate-in" style="text-align:center;color:var(--text-muted);font-size:13px">
    <i class="fas fa-circle-info" style="color:var(--accent)"></i>
    Masih bingung? Ketik <code style="background:var(--bg-hover);padding:2px 6px;border-radius:6px">help</code>
    di Telegram kapan aja untuk memunculkan daftar ini lagi.
</div>

<style>
    .help-chip {
        display:inline-flex; align-items:center; gap:6px;
        background:var(--bg-elevated); border:1px solid var(--border);
        color:var(--text-secondary); font-family:'SF Mono',ui-monospace,Menlo,monospace;
        font-size:12px; padding:6px 11px; border-radius:8px; cursor:pointer;
        transition:border-color .15s, color .15s, background .15s;
        -webkit-tap-highlight-color:transparent;
    }
    .help-chip:hover { border-color:var(--border-focus); color:var(--text-primary); }
    .help-chip.copied { border-color:var(--green); color:var(--green); }
</style>

@endsection

@section('scripts')
<script>
    document.querySelectorAll('.help-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var text = chip.getAttribute('data-copy');
            var done = function () {
                var original = chip.innerHTML;
                chip.classList.add('copied');
                chip.innerHTML = '<i class="fas fa-check" style="font-size:10px"></i> Tersalin';
                setTimeout(function () { chip.classList.remove('copied'); chip.innerHTML = original; }, 1400);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(done);
            } else {
                done();
            }
        });
    });
</script>
@endsection
