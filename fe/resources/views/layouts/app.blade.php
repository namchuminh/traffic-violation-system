{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','Hệ thống vi phạm giao thông')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-900">
<div class="min-h-screen flex">
    {{-- SIDEBAR --}}
    <aside class="w-[280px] shrink-0 text-white bg-gradient-to-b from-indigo-800 via-indigo-900 to-slate-950 flex flex-col">
        <div class="h-16 px-5 flex items-center border-b border-white/10">
            <div class="w-10 h-10 rounded-2xl bg-white/10 ring-1 ring-white/10 flex items-center justify-center">
                <svg viewBox="0 0 24 24" class="w-5 h-5 fill-current text-emerald-400">
                    <path d="M4 4h16v12H5.5L4 17.5V4zm3 3h10v2H7V7zm0 4h7v2H7v-2z"/>
                </svg>
            </div>
            <div class="ml-3 leading-tight">
                <div class="text-sm font-semibold tracking-wide">HỆ THỐNG GIÁM SÁT</div>
                <div class="text-[10px] text-white/50 uppercase">Phát hiện vi phạm AI</div>
            </div>
        </div>

        @php
            $userRole = auth()->user()->role ?? 'viewer';
            
            // Định nghĩa danh sách menu gốc
            $fullNav = [
                ['route' => 'dashboard', 'label' => 'Bảng điều khiển', 'icon' => 'M4 13h7V4H4v9zm9 7h7V11h-7v9zM4 20h7v-5H4v5zm9-9h7V4h-7v7z', 'roles' => ['admin', 'supervisor', 'viewer']],
                ['route' => 'videos.index', 'label' => 'Xử lý Video', 'icon' => 'M4 5h10v14H4V5zm12 4 4-2v10l-4-2V9z', 'roles' => ['admin', 'supervisor']],
                ['route' => 'violations.index', 'label' => 'Danh sách vi phạm', 'icon' => 'M12 2 2 22h20L12 2zm0 6 6 12H6l6-12zm-1 4v4h2v-4h-2z', 'roles' => ['admin', 'supervisor', 'viewer']],
                ['route' => 'zones-rules.index', 'label' => 'Vùng & Luật', 'icon' => 'M4 4h16v4H4V4zm0 6h7v10H4V10zm9 0h7v10h-7V10z', 'roles' => ['admin']],
                ['route' => 'users.index', 'label' => 'Quản lý nhân sự', 'icon' => 'M16 11c1.66 0 3-1.34 3-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V20h7v-3.5c0-2.33-4.67-3.5-7-3.5z', 'roles' => ['admin']],
            ];

            // Lọc menu dựa trên quyền của người dùng hiện tại
            $nav = array_filter($fullNav, function($item) use ($userRole) {
                return in_array($userRole, $item['roles']);
            });
        @endphp

        <nav class="p-3 space-y-1">
            @foreach($nav as $item)
                @php $active = request()->routeIs($item['route'].'*'); @endphp
                <a href="{{ route($item['route']) }}"
                   class="group flex items-center gap-3 px-3 py-2 rounded-2xl transition
                          {{ $active ? 'bg-white/15 ring-1 ring-white/10 shadow-lg' : 'hover:bg-white/10' }}">
                    <span class="w-10 h-10 rounded-2xl {{ $active ? 'bg-indigo-500 text-white' : 'bg-white/10 text-white/70' }} ring-1 ring-white/10 flex items-center justify-center transition-all group-hover:scale-105">
                        <svg viewBox="0 0 24 24" class="w-5 h-5 fill-current">
                            <path d="{{ $item['icon'] }}"></path>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold truncate {{ $active ? 'text-white' : 'text-white/80 group-hover:text-white' }}">
                            {{ $item['label'] }}
                        </div>
                        <div class="text-[10px] {{ $active ? 'text-emerald-400' : 'text-white/40' }} truncate">
                            {{ $active ? 'Đang truy cập' : 'Xem mục này' }}
                        </div>
                    </div>
                    @if($active)
                        <span class="ml-auto w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.6)]"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="px-4 mt-auto pb-6">
            <div class="p-4 rounded-3xl bg-gradient-to-br from-white/10 to-transparent border border-white/10 shadow-inner">
                <div class="flex items-center gap-2 mb-2">
                    <span class="relative flex h-2 w-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-white/50">Hệ thống AI</span>
                </div>
                <div class="text-xs font-medium text-white/90 leading-relaxed">
                    Trình giám sát đang hoạt động ổn định
                </div>
                
                {{-- Hiển thị chức vụ hiện tại --}}
                <div class="mt-3 pt-3 border-t border-white/10">
                    <div class="text-[10px] text-white/40 uppercase tracking-tighter">Quyền hạn của bạn</div>
                    <div class="text-xs font-bold text-indigo-300">
                        @if($userRole === 'admin') Quản trị viên hệ thống
                        @elseif($userRole === 'supervisor') Giám sát viên vận hành
                        @else Người xem báo cáo @endif
                    </div>
                </div>
            </div>
        </div>
    </aside>

    {{-- MAIN --}}
    <div class="flex-1 min-w-0 flex flex-col">
        {{-- TOPBAR --}}
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 shrink-0 shadow-sm z-10">
            <div class="min-w-0">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">@yield('breadcrumb', 'Bảng điều khiển')</div>
                <div class="text-lg font-bold text-slate-800 truncate">@yield('page_title', 'Bảng điều khiển')</div>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden lg:flex items-center gap-3 px-4 py-2 rounded-2xl bg-slate-100 border border-slate-200 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:border-indigo-500 transition-all">
                    <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10 18a8 8 0 1 1 5.293-14.293A8 8 0 0 1 10 18zm11 3-6-6 1.414-1.414 6 6L21 21z"/>
                    </svg>
                    <input class="bg-transparent outline-none text-sm w-64 placeholder:text-slate-400 font-medium" placeholder="Tìm kiếm nhanh..."/>
                </div>

                <div class="h-8 w-px bg-slate-200"></div>

                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-sm font-bold text-slate-800 leading-none mb-1">{{ auth()->user()->name ?? 'Khách' }}</div>
                        <div class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100 uppercase">
                            {{ $userRole }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-slate-900 shadow-lg shadow-slate-200 text-white flex items-center justify-center font-bold text-sm ring-2 ring-white">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                    
                    <form method="POST" action="{{ route('logout') }}" class="ml-1">
                        @csrf
                        <button class="p-2.5 rounded-2xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Đăng xuất">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="p-6 overflow-y-auto">
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>