@extends('layouts.tenant')

@section('content')
    <x-ui.card title="Module placeholder" description="Route final sudah tersedia, jadi milestone berikutnya bisa langsung mengisi query, form, dan aksi tanpa ubah struktur navigasi.">
        <x-ui.state-shell :title="$stateTitle" description="Halaman ini sengaja masih berupa shell karena milestone 0 hanya mengunci fondasi dan kontrak area tenant." tone="neutral" />
    </x-ui.card>
@endsection
