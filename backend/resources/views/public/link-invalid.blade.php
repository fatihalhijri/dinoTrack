@php
    /** @var string $businessName */
@endphp
@extends('public.layout')

@section('title', 'Link Tidak Valid')

@section('content')
    <h1>Link tagihan tidak valid</h1>
    <p>Link yang Anda buka tidak lengkap atau sudah diubah. Buka kembali link tagihan dari pesan WhatsApp kami tanpa mengubahnya.</p>
    <a class="btn btn-primary" href="{{ route('isolation.show') }}">Cek tagihan dengan kode pelanggan</a>
@endsection
