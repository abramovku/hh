<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Админ-панель')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14 items-center">
                <div class="flex items-center gap-6">
                    <span class="font-semibold text-gray-800">HHStaff</span>
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="text-sm {{ request()->routeIs('admin.dashboard') ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-gray-900' }}"
                    >
                        Дашборд
                    </a>
                    <a
                        href="{{ route('admin.responses') }}"
                        class="text-sm {{ request()->routeIs('admin.responses') ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-gray-900' }}"
                    >
                        Отклики
                    </a>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-red-600 transition">
                        Выйти
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>
</body>
</html>
