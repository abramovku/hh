@extends('admin.layout')

@section('title', 'Дашборд')

@section('content')
    <h1 class="text-xl font-semibold text-gray-800 mb-6">Статистика откликов</h1>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Всего откликов</p>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total'], 0, '.', ' ') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Сегодня</p>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['today'], 0, '.', ' ') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">За неделю</p>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['week'], 0, '.', ' ') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">За месяц</p>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['month'], 0, '.', ' ') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-5">
            <p class="text-xs text-blue-500 uppercase tracking-wide mb-1">Звонков</p>
            <p class="text-3xl font-bold text-blue-700">{{ number_format($stats['called'], 0, '.', ' ') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-green-100 p-5">
            <p class="text-xs text-green-500 uppercase tracking-wide mb-1">SMS</p>
            <p class="text-3xl font-bold text-green-700">{{ number_format($stats['sms'], 0, '.', ' ') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-emerald-100 p-5">
            <p class="text-xs text-emerald-500 uppercase tracking-wide mb-1">WhatsApp</p>
            <p class="text-3xl font-bold text-emerald-700">{{ number_format($stats['whatsapp'], 0, '.', ' ') }}</p>
        </div>
    </div>
@endsection
