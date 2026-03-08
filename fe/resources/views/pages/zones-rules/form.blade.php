@extends('layouts.app')
@section('title', $mode === 'create' ? 'Thêm vùng' : 'Sửa vùng')
@section('breadcrumb','Vùng & Luật')
@section('page_title', $mode === 'create' ? 'Thêm vùng' : 'Sửa vùng')

@section('content')
<div class="max-w-4xl">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <div class="font-semibold">{{ $mode === 'create' ? 'Thêm vùng' : 'Sửa vùng' }}</div>
                <div class="text-sm text-slate-500">Dán tọa độ Roboflow np.array(...) hoặc JSON</div>
            </div>
            <a href="{{ route('zones-rules.index') }}"
               class="px-3 py-2 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">
                Quay lại
            </a>
        </div>

        <form method="POST"
              action="{{ $mode === 'create' ? route('zones-rules.store') : route('zones-rules.update', $zone) }}"
              class="p-5 space-y-4">
            @csrf
            @if($mode === 'edit')
                @method('PUT')
            @endif

            <div>
                <label class="text-sm text-slate-600">Tên vùng</label>
                <input name="name" value="{{ old('name', $zone->name) }}"
                       class="mt-2 w-full px-4 py-2.5 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-sm" />
                @error('name') <div class="mt-1 text-xs text-rose-700">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm text-slate-600">Tốc độ tối đa (km/h)</label>
                <input name="max_speed" value="{{ old('max_speed', $zone->max_speed) }}"
                       class="mt-2 w-full px-4 py-2.5 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-sm" />
                @error('max_speed') <div class="mt-1 text-xs text-rose-700">{{ $message }}</div> @enderror
            </div>

            <div></div>
                <label class="text-sm text-slate-600">Tọa độ (dán vào đây)</label>
                <textarea name="coordinates_text" rows="10"
                          class="mt-2 w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-xs font-mono"
                          placeholder='[ np.array([[...]]), np.array([[...]]) ] hoặc JSON'>{{ old('coordinates_text', $zone->roboflow_coordinates['raw'] ?? '') }}</textarea>
                @error('coordinates_text') <div class="mt-1 text-xs text-rose-700">{{ $message }}</div> @enderror
            </div>

            <div class="flex items-center gap-2 mt-4">
                <button class="px-4 py-2 rounded-2xl bg-indigo-700 text-white text-sm hover:bg-indigo-800">
                    {{ $mode === 'create' ? 'Tạo' : 'Cập nhật' }}
                </button>
                <a href="{{ route('zones-rules.index') }}"
                   class="px-4 py-2 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">
                    Huỷ
                </a>
            </div>
        </form>
    </div>
</div>
@endsection