{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.guest')
@section('title','Đăng nhập')

@section('content')
<div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">
    <div class="hidden lg:block relative overflow-hidden bg-gradient-to-b from-indigo-800 via-indigo-900 to-slate-950">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-white/20 blur-3xl"></div>
            <div class="absolute -bottom-32 -right-20 w-[520px] h-[520px] rounded-full bg-sky-400/20 blur-3xl"></div>
        </div>
        <div class="relative h-full p-10 flex flex-col justify-between">
            <div class="flex items-center gap-3 text-white">
                <div class="w-11 h-11 rounded-2xl bg-white/10 ring-1 ring-white/10 flex items-center justify-center">
                    <svg viewBox="0 0 24 24" class="w-6 h-6 fill-current">
                        <path d="M4 4h16v12H5.5L4 17.5V4zm3 3h10v2H7V7zm0 4h7v2H7v-2z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-lg font-semibold leading-tight">Hệ thống giám sát</div>
                    <div class="text-sm text-white/70">Vi phạm giao thông</div>
                </div>
            </div>

            <div class="text-white">
                <div class="text-3xl font-semibold leading-tight">Theo dõi phát hiện vi phạm</div>
                <div class="mt-2 text-white/70 max-w-md">
                    Tải video, phát hiện vi phạm, quản lý xử lý, xuất biên bản PDF.
                </div>
                <div class="mt-6 grid grid-cols-3 gap-3 max-w-md">
                    <div class="p-3 rounded-2xl bg-white/10 ring-1 ring-white/10">
                        <div class="text-xs text-white/70">Sự kiện</div>
                        <div class="text-lg font-semibold">Trực tiếp</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/10 ring-1 ring-white/10">
                        <div class="text-xs text-white/70">Quy trình</div>
                        <div class="text-lg font-semibold">Duyệt</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/10 ring-1 ring-white/10">
                        <div class="text-xs text-white/70">Xuất</div>
                        <div class="text-lg font-semibold">PDF</div>
                    </div>
                </div>
            </div>

            <div class="text-xs text-white/60">v1.0 • Laravel + Tailwind (CDN)</div>
        </div>
    </div>

    <div class="flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6">
                <div class="text-xl font-semibold">Đăng nhập</div>
                <div class="text-sm text-slate-500 mt-1">Vui lòng nhập thông tin để tiếp tục</div>

                {{-- Session status --}}
                @if (session('status'))
                    <div class="mt-4 p-3 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm">
                        {{ session('status') }}
                    </div>
                @endif

                {{-- Validation errors --}}
                @if ($errors->any())
                    <div class="mt-4 p-3 rounded-2xl bg-rose-50 text-rose-700 border border-rose-200 text-sm">
                        <div class="font-semibold mb-1">Có lỗi xảy ra:</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                    @csrf

                    <div>
                        <label class="text-sm font-medium">Email</label>
                        <input
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            placeholder="VD: admin@gmail.com"
                            class="mt-2 w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 placeholder:text-slate-400"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium">Mật khẩu</label>
                        <input
                            name="password"
                            type="password"
                            required
                            placeholder="Nhập mật khẩu"
                            class="mt-2 w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 placeholder:text-slate-400"
                        />
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input name="remember" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-200">
                            Ghi nhớ đăng nhập
                        </label>

                        @if (Route::has('password.request'))
                            <a class="text-sm font-medium text-indigo-700 hover:text-indigo-800" href="{{ route('password.request') }}">
                                Quên mật khẩu?
                            </a>
                        @endif
                    </div>

                    <button class="w-full py-3 rounded-2xl bg-indigo-700 text-white font-semibold hover:bg-indigo-800 transition">
                        Đăng nhập
                    </button>
                </form>
            </div>

            <div class="text-xs text-slate-500 mt-4 text-center">
                Dùng tài khoản demo nếu đã cấu hình.
            </div>
        </div>
    </div>
</div>
@endsection