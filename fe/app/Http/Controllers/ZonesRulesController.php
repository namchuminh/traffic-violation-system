<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZonesRulesController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $zones = Zone::query()
            ->when($q !== '', fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->paginate(10)
            ->appends(request()->query());

        return view('pages.zones-rules.index', compact('zones', 'q'));
    }

    public function create()
    {
        return view('pages.zones-rules.form', [
            'mode' => 'create',
            'zone' => new Zone(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, null);
        Zone::create($data);

        return redirect()->route('zones-rules.index')->with('success', 'Đã tạo vùng');
    }

    public function edit(Zone $zone)
    {
        return view('pages.zones-rules.form', [
            'mode' => 'edit',
            'zone' => $zone,
        ]);
    }

    public function update(Request $request, Zone $zone)
    {
        $data = $this->validatePayload($request, $zone->id);
        $zone->update($data);

        return redirect()->route('zones-rules.index')->with('success', 'Đã cập nhật vùng');
    }

    public function destroy(Zone $zone)
    {
        $zone->delete();

        return redirect()->route('zones-rules.index')->with('success', 'Đã xoá vùng');
    }

    private function validatePayload(Request $request, ?int $ignoreId): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('zones', 'name')->ignore($ignoreId)],
            'coordinates_text' => ['required', 'string'],

            // max_speed: km/h
            'max_speed' => ['nullable', 'integer', 'min:0', 'max:300'],
        ]);

        $roboflow = $this->parseCoordinatesText($validated['coordinates_text']);

        return [
            'name' => $validated['name'],
            'max_speed' => $validated['max_speed'] ?? null,
            'roboflow_coordinates' => $roboflow,
        ];
    }

    private function parseCoordinatesText(string $text): array
    {
        $t = trim($text);

        $json = json_decode($t, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return ['raw' => $t, 'polygons' => $json];
        }

        preg_match_all('/np\.array\(\s*(\[\[.*?\]\])\s*\)/s', $t, $m);
        $polys = [];

        if (!empty($m[1])) {
            foreach ($m[1] as $chunk) {
                $pts = json_decode($chunk, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($pts)) {
                    $polys[] = $pts;
                }
            }
        }

        if (count($polys) >= 1) {
            return ['raw' => $t, 'polygons' => $polys];
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'coordinates_text' => 'Tọa độ không hợp lệ. Dán đúng output Roboflow np.array(...) hoặc JSON.',
        ]);
    }
}