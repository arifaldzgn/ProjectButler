@extends('layouts.webview')
@section('title', 'Setup — Akun')
@section('content')

<div class="progress-wrap animate-in">
    <div class="progress-label">Langkah 2 dari 5</div>
    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:40%"></div></div>
</div>

<div class="page-header animate-in animate-in-delay-1">
    <div class="butler-mark">Butler Setup</div>
    <h1>Di mana kamu<br>simpan uang?</h1>
    <p class="subtitle">Tambahkan akun yang sering kamu pakai. Saldo awal boleh dikosongkan.</p>
</div>

<div class="form-body animate-in animate-in-delay-2" x-data="accountManager()" x-init="init()" x-cloak>

    {{-- Quick suggestions --}}
    <div class="field">
        <div class="field-label">Pilih cepat</div>
        <div class="chips">
            <template x-for="s in suggestions" :key="s.name">
                <button type="button" class="chip" :class="{ active: isAdded(s.name) }"
                        @click="toggleSuggestion(s)" x-text="s.name"></button>
            </template>
        </div>
    </div>

    {{-- Account list --}}
    <template x-for="(account, index) in accounts" :key="index">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title" x-text="account.name"></div>
                    <div class="card-subtitle" x-text="account.type_label"></div>
                </div>
                <button type="button" class="remove-btn" @click="remove(index)">✕</button>
            </div>
            <input type="number" placeholder="Saldo awal (opsional)"
                   x-model="account.balance"
                   style="margin-top:12px" min="0">
            <div class="radio-row" @click="defaultIndex = index">
                <div class="radio-visual" :class="{ 'is-checked': defaultIndex === index }"></div>
                <span>Akun utama untuk pengeluaran harian</span>
            </div>
        </div>
    </template>

    {{-- Add custom account --}}
    <div x-show="showCustomForm" class="card" style="border-color: rgba(255,255,255,0.12)">
        <div class="field" style="margin-bottom:12px">
            <label class="field-label">Nama akun</label>
            <input type="text" x-model="customName" placeholder="contoh: Jenius, Seabank">
        </div>
        <div class="field" style="margin-bottom:12px">
            <label class="field-label">Tipe</label>
            <select x-model="customType">
                <option value="bank">Bank</option>
                <option value="ewallet">E-wallet</option>
                <option value="cash">Cash</option>
                <option value="other">Lainnya</option>
            </select>
        </div>
        <div style="display:flex;gap:8px">
            <button type="button" class="btn btn-primary" style="flex:1;padding:11px" @click="addCustom()">Tambah</button>
            <button type="button" class="btn btn-ghost" style="flex:1;padding:11px;margin-top:0" @click="showCustomForm=false">Batal</button>
        </div>
    </div>

    <button type="button" class="btn btn-ghost" x-show="!showCustomForm" @click="showCustomForm=true">
        + Tambah akun lain
    </button>

    {{-- Hidden form --}}
    <form method="POST" action="{{ route('onboarding.accounts.save', $telegram_id) }}" id="accountsForm">
        @csrf
        <template x-for="(account, index) in accounts" :key="index">
            <div>
                <input type="hidden" :name="'accounts[' + index + '][name]'"    :value="account.name">
                <input type="hidden" :name="'accounts[' + index + '][type]'"    :value="account.type">
                <input type="hidden" :name="'accounts[' + index + '][balance]'" :value="account.balance || 0">
            </div>
        </template>
        <input type="hidden" name="default_account_index" x-model="defaultIndex">
    </form>

    <div class="form-footer">
        <button type="button" class="btn btn-primary"
                :disabled="accounts.length === 0"
                @click="submitForm()">
            Lanjut →
        </button>
    </div>
</div>

<script>
function accountManager() {
    return {
        accounts: [],
        defaultIndex: 0,
        showCustomForm: false,
        customName: '',
        customType: 'bank',
        suggestions: [
            { name: 'BCA',     type: 'bank',    type_label: 'Bank' },
            { name: 'Mandiri', type: 'bank',    type_label: 'Bank' },
            { name: 'BRI',     type: 'bank',    type_label: 'Bank' },
            { name: 'BNI',     type: 'bank',    type_label: 'Bank' },
            { name: 'GoPay',   type: 'ewallet', type_label: 'E-wallet' },
            { name: 'OVO',     type: 'ewallet', type_label: 'E-wallet' },
            { name: 'Dana',    type: 'ewallet', type_label: 'E-wallet' },
            { name: 'Cash',    type: 'cash',    type_label: 'Cash' },
        ],
        init() {},
        isAdded(name) { return this.accounts.some(a => a.name === name); },
        toggleSuggestion(s) {
            if (this.isAdded(s.name)) {
                this.accounts = this.accounts.filter(a => a.name !== s.name);
                if (this.defaultIndex >= this.accounts.length) this.defaultIndex = 0;
            } else {
                this.accounts.push({ name: s.name, type: s.type, type_label: s.type_label, balance: '' });
            }
        },
        addCustom() {
            if (!this.customName.trim()) return;
            const labels = { bank: 'Bank', ewallet: 'E-wallet', cash: 'Cash', other: 'Lainnya' };
            this.accounts.push({
                name: this.customName.trim(),
                type: this.customType,
                type_label: labels[this.customType],
                balance: ''
            });
            this.customName = '';
            this.showCustomForm = false;
        },
        remove(index) {
            this.accounts.splice(index, 1);
            if (this.defaultIndex >= this.accounts.length) this.defaultIndex = 0;
        },
        submitForm() {
            if (this.accounts.length === 0) return;
            document.getElementById('accountsForm').submit();
        }
    }
}
</script>

@endsection
