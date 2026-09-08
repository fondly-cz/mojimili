@php
    $accepted = $calculation->items->where('is_accepted', true);
@endphp

<x-mail::message>
# Kalkulace byla potvrzena

Zákazník **{{ $calculation->customer_name }}**@if ($calculation->customer_company) ({{ $calculation->customer_company }})@endif právě potvrdil cenovou nabídku #{{ str_pad((string) $calculation->id, 6, '0', STR_PAD_LEFT) }}.

- **Kontakt:** {{ $calculation->customer_email }}, {{ $calculation->customer_phone }}
- **Počet potvrzených položek:** {{ $accepted->count() }}
- **Celková cena (bez DPH):** {{ number_format((float) $calculation->total_price, 0, ',', ' ') }} Kč
- **Předpokládaná realizace:** {{ $calculation->total_days }} pracovních dní

<x-mail::button :url="$url">
Zobrazit kalkulaci
</x-mail::button>

Tato zpráva byla odeslána automaticky po potvrzení nabídky zákazníkem.

{{ config('app.name') }}
</x-mail::message>
