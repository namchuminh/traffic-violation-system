@extends('layouts.app')
@section('title', $mode === 'create' ? 'Thêm người dùng' : 'Sửa người dùng')
@section('breadcrumb','Người dùng & Vai trò')
@section('page_title', $mode === 'create' ? 'Thêm người dùng' : 'Sửa người dùng')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <div class="font-semibold">{{ $mode === 'create' ? 'Thêm người dùng' : 'Sửa người dùng' }}</div>
                <div class="text-sm text-slate-500">Phân quyền: admin / supervisor / viewer</div>
            </div>
            <a href="{{ route('users.index') }}"
               class="px-3 py-2 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">
                Quay lại
            </a>
        </div>

        <form method="POST"
              action="{{ $mode === 'create' ? route('users.store') : route('users.update', $user) }}"
              class="p-5 space-y-4">
            @csrf
            @if($mode === 'edit')
                @method('PUT')
            @endif

            <div>
                <label class="text-sm text-slate-600">Họ tên</label>
                <input name="name" value="{{ old('name', $user->name) }}"
                       class="mt-2 w-full px-4 py-2.5 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-sm" />
                @error('name') <div class="mt-1 text-xs text-rose-700">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm text-slate-600">Email</label>
                <input name="email" value="{{ old('email', $user->email) }}"
                       class="mt-2 w-full px-4 py-2.5 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-sm" />
                @error('email') <div class="mt-1 text-xs text-rose-700">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm text-slate-600">
                    Mật khẩu {{ $mode === 'edit' ? '(để trống nếu không đổi)' : '' }}
                </label>
                <input type="password" name="password"
                       class="mt-2 w-full px-4 py-2.5 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-sm" />
                @error('password') <div class="mt-1 text-xs text-rose-700">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm text-slate-600">Vai trò</label>
                @php $currentRole = old('role', $user->role ?? 'viewer'); @endphp
                <select name="role"
                        class="mt-2 w-full px-4 py-2.5 rounded-2xl border border-slate-200 bg-white outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-sm">
                    @foreach($roles as $key => $label)
                        <option value="{{ $key }}" @selected($currentRole === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role') <div class="mt-1 text-xs text-rose-700">{{ $message }}</div> @enderror
            </div>

            <div class="flex items-center gap-2">
                <button class="px-4 py-2 rounded-2xl bg-indigo-700 text-white text-sm hover:bg-indigo-800">
                    {{ $mode === 'create' ? 'Tạo' : 'Cập nhật' }}
                </button>
                <a href="{{ route('users.index') }}"
                   class="px-4 py-2 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">
                    Huỷ
                </a>
            </div>
        </form>
    </div>
</div>
@endsection