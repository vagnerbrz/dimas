@extends('layouts.app')

@section('header', 'Preview Interativo')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
        <div class="mb-6">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Funcionalidade em Manutenção</h2>
            <p class="text-gray-600 mb-6">
                O laboratório de preview interativo está temporariamente desativado.
            </p>
        </div>

        <div class="bg-gray-50 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Status do Sistema</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-700">Preview Interativo</span>
                        <span class="px-3 py-1 bg-red-100 text-red-800 text-sm font-medium rounded-full">Desativado</span>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-700">Testes WhatsApp</span>
                        <span class="px-3 py-1 bg-red-100 text-red-800 text-sm font-medium rounded-full">Indisponível</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-left max-w-2xl mx-auto">
            <h3 class="text-lg font-semibold text-gray-700 mb-3">Funcionalidades Temporariamente Indisponíveis:</h3>
            <ul class="space-y-2 text-gray-600 mb-6">
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Teste de botões interativos (Buttons Legados)</span>
                </li>
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Teste de botões booleanos (Sim/Não)</span>
                </li>
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Teste de listas interativas (List Message)</span>
                </li>
            </ul>
        </div>

        <div class="pt-6 border-t border-gray-200">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Voltar para o Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
                            value="{{ old('phone', '5592981220709') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            placeholder="5592981220709 ou 74655188680788@lid"
                        >
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Enviar Preview com Buttons
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-base font-semibold text-slate-900">Confirmacao Sim/Nao</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Experimento de resposta rapida com duas opcoes objetivas. A intencao aqui e validar aquele formato curtinho de confirmacao.
                </p>

                <form method="POST" action="{{ route('whatsapp.preview.boolean') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="phone-boolean" class="block text-sm font-medium text-slate-700">Telefone ou chatId</label>
                        <input
                            id="phone-boolean"
                            name="phone"
                            type="text"
                            value="{{ old('phone', '5592981220709') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            placeholder="5592981220709 ou 74655188680788@lid"
                        >
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                    >
                        Enviar Preview Sim/Nao
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-base font-semibold text-slate-900">List Message</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Envia uma lista interativa com ate tres produtos ativos do cardapio atual.
                </p>

                <form method="POST" action="{{ route('whatsapp.preview.list') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="phone-list" class="block text-sm font-medium text-slate-700">Telefone ou chatId</label>
                        <input
                            id="phone-list"
                            name="phone"
                            type="text"
                            value="{{ old('phone', '5592981220709') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            placeholder="5592981220709 ou 74655188680788@lid"
                        >
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500"
                    >
                        Enviar Preview com Lista
                    </button>
                </form>
            </div>
        </div>

        @if (session('preview_response'))
            <div class="bg-slate-950 rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-semibold text-slate-100">Retorno bruto do WPPConnect</h3>
                <pre class="mt-4 overflow-x-auto text-xs leading-6 text-slate-300">{{ session('preview_response') }}</pre>
            </div>
        @endif
    </div>
@endsection
