@extends('layouts.app')

@section('header', 'Impressao de Pedidos')

@section('content')
<div class="max-w-6xl mx-auto">
    @livewire('print-jobs-monitor')
</div>
@endsection
