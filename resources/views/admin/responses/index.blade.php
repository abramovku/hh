@extends('admin.layout')

@section('title', 'Отклики')

@section('content')
    <h1 class="text-xl font-semibold text-gray-800 mb-4">Отклики</h1>

    {{-- Фильтры --}}
    <form method="GET" action="{{ route('admin.responses') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Телефон</label>
            <input
                type="text"
                name="phone"
                value="{{ request('phone') }}"
                placeholder="+7..."
                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Vacancy ID</label>
            <input
                type="text"
                name="vacancy"
                value="{{ request('vacancy') }}"
                placeholder="HH vacancy ID"
                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>
        <div class="flex items-center gap-4 pb-0.5">
            <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="called" value="1" {{ request()->boolean('called') ? 'checked' : '' }} class="rounded">
                Звонок
            </label>
            <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="sms" value="1" {{ request()->boolean('sms') ? 'checked' : '' }} class="rounded">
                SMS
            </label>
            <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="whatsapp" value="1" {{ request()->boolean('whatsapp') ? 'checked' : '' }} class="rounded">
                WhatsApp
            </label>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition">
            Найти
        </button>
        <a href="{{ route('admin.responses') }}" class="text-sm text-gray-500 hover:text-gray-700 py-1.5">
            Сбросить
        </a>
    </form>

    {{-- Таблица --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3">HH resp. ID</th>
                    <th class="px-4 py-3">Vacancy HH</th>
                    <th class="px-4 py-3">Estaff кандидат</th>
                    <th class="px-4 py-3">Телефон</th>
                    <th class="px-4 py-3">Создан</th>
                    <th class="px-4 py-3">Синхронизирован</th>
                    <th class="px-3 py-3 text-center">Звонок</th>
                    <th class="px-3 py-3 text-center">SMS</th>
                    <th class="px-3 py-3 text-center">WA</th>
                    <th class="px-4 py-3">Ошибка</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($responses as $response)
                    @php
                        $phone = $response->meta->first()?->value ?? '—';
                        $types = $response->contactEvents->pluck('type')->unique();
                        $hasSent = ! is_null($response->sent_at);
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono text-gray-700">{{ $response->response_id }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $response->vacancy_id }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $response->candidate_estaff ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $phone }}</td>
                        <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">{{ $response->created_at?->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-2.5 text-center">
                            @if ($hasSent)
                                <span class="text-green-600" title="{{ $response->sent_at->format('d.m.Y H:i') }}">&#10003;</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if ($types->contains('call'))
                                <span class="text-green-600">&#10003;</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if ($types->contains('sms'))
                                <span class="text-green-600">&#10003;</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if ($types->contains('whatsapp'))
                                <span class="text-green-600">&#10003;</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-red-500 text-xs max-w-xs truncate">
                            {{ $response->error ?? '' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-400">Откликов не найдено</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Пагинация --}}
    @if ($responses->hasPages())
        <div class="mt-4">
            {{ $responses->links() }}
        </div>
    @endif
@endsection
