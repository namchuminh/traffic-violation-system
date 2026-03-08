@extends('layouts.app')
@section('title','Người dùng & Vai trò')
@section('breadcrumb','Người dùng & Vai trò')
@section('page_title','Người dùng & Vai trò')

@section('content')
<div class="space-y-4">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="font-semibold">Người dùng</div>
                <div class="text-sm text-slate-500">Quản lý tài khoản và phân quyền</div>
            </div>

            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-center gap-2">
                    <input name="q" value="{{ $q }}"
                           placeholder="Tìm theo tên/email..."
                           class="w-64 px-4 py-2.5 rounded-2xl border border-slate-200 bg-white outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-sm" />

                    <select name="role" class="px-3 py-2.5 rounded-2xl border border-slate-200 bg-white text-sm">
                        <option value="">Tất cả vai trò</option>
                        @foreach($roles as $key => $label)
                            <option value="{{ $key }}" @selected($role === $key)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <button class="px-3 py-2.5 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">
                        Tìm
                    </button>
                </form>

                <a href="{{ route('users.create') }}"
                   class="px-3 py-2.5 rounded-2xl bg-indigo-700 text-white text-sm hover:bg-indigo-800">
                    Thêm người dùng
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="px-5 py-3 text-sm bg-emerald-50 text-emerald-700 border-b border-emerald-100">
                {{ session('success') }}
            </div>
        @endif

        <div class="p-5 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-slate-500">
                <tr class="border-b border-slate-200">
                    <th class="text-left py-3 pr-3">Họ tên</th>
                    <th class="text-left py-3 pr-3">Email</th>
                    <th class="text-left py-3 pr-3">Vai trò</th>
                    <th class="text-left py-3 pr-3">Cập nhật</th>
                    <th class="text-right py-3">Thao tác</th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    @php
                        $roleKey = $u->role ?? 'viewer';
                        $roleLabel = $roles[$roleKey] ?? $roleKey;

                        $tone = match($roleKey) {
                            'admin' => 'rose',
                            'supervisor' => 'amber',
                            default => 'slate',
                        };
                    @endphp

                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="py-4 pr-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-semibold">
                                    {{ strtoupper(mb_substr($u->name,0,1)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold truncate">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-500 truncate">ID: {{ $u->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 pr-3 text-slate-700">{{ $u->email }}</td>
                        <td class="py-4 pr-3">
                            <span class="text-xs px-2.5 py-1 rounded-full border
                                {{ $tone==='rose'?'bg-rose-50 text-rose-700 border-rose-200':'' }}
                                {{ $tone==='amber'?'bg-amber-50 text-amber-700 border-amber-200':'' }}
                                {{ $tone==='slate'?'bg-slate-50 text-slate-700 border-slate-200':'' }}">
                                {{ $roleLabel }}
                            </span>
                        </td>
                        <td class="py-4 pr-3 text-slate-600">{{ optional($u->updated_at)->format('Y-m-d H:i') }}</td>
                        <td class="py-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('users.edit', $u) }}"
                                   class="px-3 py-2 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">
                                    Sửa
                                </a>

                                <form method="POST" action="{{ route('users.destroy', $u) }}"
                                      onsubmit="return confirm('Xoá người dùng này?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-2 rounded-2xl bg-rose-600 text-white text-sm hover:bg-rose-700">
                                        Xoá
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="py-6 text-slate-500" colspan="5">Không có dữ liệu.</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
@endsection