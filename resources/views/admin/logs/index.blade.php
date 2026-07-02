@extends('admin.layout')

@section('title', 'Логи')

@section('content')
    <h1 class="text-xl font-semibold text-gray-800 mb-4">Логи</h1>

    {{-- Фильтры --}}
    <form method="GET" action="{{ route('admin.logs') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Канал</label>
            <select name="channel" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Все</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel }}" {{ request('channel') === $channel ? 'selected' : '' }}>{{ $channel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Уровень</label>
            <select name="level" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Все</option>
                @foreach ($levels as $level)
                    <option value="{{ $level }}" {{ request('level') === $level ? 'selected' : '' }}>{{ $level }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Сообщение</label>
            <input
                type="text"
                name="message"
                value="{{ request('message') }}"
                placeholder="Поиск по тексту..."
                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">С даты</label>
            <input
                type="date"
                name="date_from"
                value="{{ request('date_from') }}"
                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">По дату</label>
            <input
                type="date"
                name="date_to"
                value="{{ request('date_to') }}"
                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition">
            Найти
        </button>
        <a href="{{ route('admin.logs') }}" class="text-sm text-gray-500 hover:text-gray-700 py-1.5">
            Сбросить
        </a>
    </form>

    {{-- Таблица --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => $sort === 'asc' ? 'desc' : 'asc', 'page' => null]) }}" class="inline-flex items-center gap-1 hover:text-gray-800">
                            Дата
                            <span class="text-gray-400">{{ $sort === 'asc' ? '↑' : '↓' }}</span>
                        </a>
                    </th>
                    <th class="px-4 py-3">Канал</th>
                    <th class="px-4 py-3">Уровень</th>
                    <th class="px-4 py-3">Сообщение</th>
                    <th class="px-4 py-3">Контекст</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    @php
                        $levelColors = [
                            'DEBUG' => 'bg-gray-100 text-gray-600',
                            'INFO' => 'bg-blue-100 text-blue-700',
                            'NOTICE' => 'bg-cyan-100 text-cyan-700',
                            'WARNING' => 'bg-yellow-100 text-yellow-700',
                            'ERROR' => 'bg-red-100 text-red-700',
                            'CRITICAL' => 'bg-red-200 text-red-800',
                            'ALERT' => 'bg-red-200 text-red-800',
                            'EMERGENCY' => 'bg-red-300 text-red-900',
                        ];
                        $badge = $levelColors[$log->level_name] ?? 'bg-gray-100 text-gray-600';
                    @endphp
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                        <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">{{ $log->channel }}</td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ $log->level_name }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-700 max-w-xl break-words">{{ $log->message }}</td>
                        <td class="px-4 py-2.5 text-gray-500">
                            @if (! empty($log->context) || ! empty($log->extra))
                                <details>
                                    <summary class="cursor-pointer text-blue-600 text-xs">Показать</summary>
                                    <pre class="mt-1 text-xs bg-gray-50 border border-gray-200 rounded-lg p-2 max-w-xl overflow-x-auto">{{ json_encode(array_filter(['context' => $log->context, 'extra' => $log->extra]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">Логи не найдены</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Пагинация --}}
    @if ($logs->hasPages())
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    @endif
@endsection
