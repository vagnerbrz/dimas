@extends('layouts.app')

@section('header', 'Gestão de Clientes')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div class="flex gap-2">
        <form action="{{ route('customers.index') }}" method="GET" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar cliente..." class="p-2 rounded-lg border border-gray-300 text-sm outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 transition">Buscar</button>
        </form>
    </div>
    <a href="{{ route('customers.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2">
        <span>+ Novo Cliente</span>
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-left">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
            <tr>
                <th class="px-6 py-3 border-b">Nome</th>
                <th class="px-6 py-3 border-b">Telefone</th>
                <th classpx-6 py-3 border-b">E-mail</th>
                <th class="px-6 py-3 border-b text-right">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($customers as $customer)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 font-medium text-gray-800">{{ $customer->name }}</td>
                    <td class="px-6 py-4 text-gray-600 text-sm">{{ $customer->phone }}</td>
                    <td class="px-6 py-4 text-gray-600 text-sm">{{ $customer->email ?? 'N/A' }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-3">
                            <a href="{{ route('customers.show', $customer->id) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm">Perfil</a>
                            <a href="{{ route('customers.edit', $customer->id) }}" class="text-gray-600 hover:text-gray-800 font-medium text-sm">Editar</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">Nenhum cliente encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4 border-t border-gray-100">
        {{ $customers->links() }}
    </div>
</div>
@endsection