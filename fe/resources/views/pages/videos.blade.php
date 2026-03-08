{{-- resources/views/pages/videos.blade.php --}}
@extends('layouts.app')
@section('title', 'Quản lý video')
@section('breadcrumb', 'Video')
@section('page_title', 'Quản lý & Xử lý Video')

@section('content')
    @php
        // Chuyển đổi dữ liệu Zone từ PHP sang JSON để JS sử dụng
        $zonesForJs = ($zones ?? collect())->map(function ($z) {
            return [
                'id' => (string)$z->id,
                'name' => $z->name,
                'polygons' => $z->roboflow_coordinates['polygons'] ?? [],
                'raw' => $z->roboflow_coordinates['raw'] ?? null,
                'max_speed' => $z->max_speed,
            ];
        })->values();
    @endphp

    <div class="space-y-4">
        {{-- Khối điều khiển Job --}}
        <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="font-semibold text-slate-800 tracking-tight">Cấu hình xử lý Video AI</div>
                    <div class="text-xs text-slate-500">Quy trình: Chọn Khu vực → Loại phát hiện → Tải video → Bắt đầu</div>
                </div>
                <div class="flex items-center gap-2">
                    <select id="detectType" class="px-3 py-2 rounded-2xl border border-slate-200 bg-white text-sm font-semibold outline-none focus:ring-4 focus:ring-indigo-100">
                        <option value="count">Đếm số lượng xe</option>
                        <option value="speeding">Quá tốc độ</option>
                        <option value="red_light">Vượt đèn đỏ/vàng</option>
                    </select>

                    <input id="videoInput" type="file" accept="video/*" class="hidden" />
                    <button id="btnPick" type="button" class="px-4 py-2 rounded-2xl border border-slate-200 text-sm font-bold hover:bg-slate-50 transition">Tải video</button>
                    <button id="btnRun" type="button" class="px-5 py-2 rounded-2xl bg-indigo-700 text-white text-sm font-bold hover:bg-indigo-800 disabled:opacity-50 transition shadow-lg shadow-indigo-200">Bắt đầu xử lý</button>
                </div>
            </div>

            <div class="p-5 grid grid-cols-1 2xl:grid-cols-3 gap-6">
                <div class="2xl:col-span-1 space-y-4">
                    {{-- Cấu hình Zone --}}
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Thông số thiết lập</div>
                        <div class="space-y-3">
                            <select id="zoneSelect" class="w-full px-3 py-2.5 rounded-2xl border border-slate-200 bg-white text-sm font-bold text-slate-700 focus:ring-4 focus:ring-indigo-50">
                                <option value="">— Chọn zone từ CSDL —</option>
                                @foreach (($zones ?? collect()) as $z)
                                    <option value="{{ $z->id }}">{{ $z->name }}</option>
                                @endforeach
                            </select>

                            <div id="maxSpeedWrap" class="hidden animate-fade-in">
                                <label class="text-[10px] font-bold text-rose-500 ml-1">Tốc độ tối đa (km/h)</label>
                                <input id="maxSpeed" type="number" class="mt-1 w-full px-3 py-2 rounded-2xl border border-slate-200 bg-white text-sm outline-none focus:border-rose-300" placeholder="vd: 60" />
                            </div>

                            <div id="hintWrap" class="text-[11px] text-slate-500 italic px-1 bg-white/50 p-2 rounded-xl border border-slate-100">
                                <div id="hintText">Đếm xe: polygons=[Zone A, (Zone B)].</div>
                            </div>
                        </div>
                        <textarea id="zones" class="mt-4 w-full h-32 px-3 py-2 rounded-2xl border border-slate-200 bg-slate-100 text-[10px] font-mono text-slate-500 outline-none" readonly placeholder="Tọa độ Polygons..."></textarea>
                        <div id="vidInfo" class="mt-2 text-[10px] text-indigo-500 font-bold font-mono">Video: — | Base: —</div>
                    </div>

                    {{-- Trạng thái thời gian thực --}}
                    <div class="p-5 rounded-2xl border border-slate-200 bg-indigo-950 text-white shadow-xl">
                        <div class="text-[10px] font-bold text-indigo-300 uppercase tracking-widest mb-4">Trình theo dõi tiến độ</div>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="opacity-60 text-xs">Job ID:</span>
                                <span id="uiJob" class="font-mono text-xs">—</span> {{-- ID uiJob --}}
                            </div>
                            <div class="flex justify-between border-b border-white/10 pb-2"><span class="opacity-60 text-xs">Trạng thái:</span><span id="uiStatus" class="font-bold text-emerald-400 uppercase tracking-tighter text-xs">—</span></div>
                            <div class="flex justify-between items-center"><span class="opacity-60 text-xs">Tiến độ:</span><span id="uiProgText" class="font-mono font-bold text-xs">0%</span></div>
                            <div class="h-1.5 rounded-full bg-white/10 overflow-hidden shadow-inner"><div id="uiProgBar" class="h-full w-0 bg-gradient-to-r from-emerald-400 to-cyan-400 transition-all duration-500"></div></div>
                            <div class="flex justify-between pt-2 border-t border-white/10"><span class="opacity-60 text-xs">Kết quả:</span><span id="uiCount" class="font-bold text-amber-300 text-xs">—</span></div>
                        </div>
                        <div id="uiErr" class="mt-4 p-2 bg-rose-500/20 border border-rose-500/50 rounded-xl text-[10px] font-mono hidden"></div>
                    </div>
                </div>

                <div class="2xl:col-span-2">
                    <div class="p-2 rounded-3xl border border-slate-200 bg-slate-900 shadow-2xl relative group">
                        <div class="absolute top-4 left-4 z-10 px-3 py-1 rounded-full bg-black/50 backdrop-blur-md text-[10px] font-bold text-white border border-white/10 shadow-lg">
                            MÀN HÌNH: <span id="uiBadge" class="text-emerald-400 uppercase tracking-widest">Idle</span>
                        </div>
                        <div class="relative aspect-video rounded-2xl overflow-hidden bg-black flex items-center justify-center border border-white/5">
                            <video id="localVideo" class="hidden w-full h-full object-contain" controls></video>
                            <canvas id="localOverlay" class="pointer-events-none absolute inset-0 hidden z-20"></canvas>
                            <video id="resultVideo" class="w-full h-full object-contain" controls></video>
                            <div id="uiMsg" class="absolute inset-0 flex items-center justify-center text-slate-600 text-sm font-medium italic pointer-events-none group-hover:opacity-30 transition-opacity">Chọn Zone và Video để xem trước thiết lập AI</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Danh sách dữ liệu từ Laravel --}}
        <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <div class="font-bold text-slate-700 uppercase text-xs tracking-widest">Lịch sử Job đã hoàn tất</div>
                <button onclick="location.reload()" class="text-[10px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-100 px-3 py-1.5 rounded-xl hover:bg-indigo-100 transition uppercase">Làm mới trang</button>
            </div>

            {{-- Bộ lọc --}}
            <form action="{{ route('videos.index') }}" method="GET" class="px-6 py-4 border-b border-slate-200 flex flex-wrap gap-3 items-center bg-white">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm tên video..." class="text-sm border border-slate-200 rounded-xl px-4 py-2 outline-none focus:ring-2 focus:ring-indigo-500 w-64">
                <select name="zone_id" class="text-sm border border-slate-200 rounded-xl px-4 py-2 outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-slate-600">
                    <option value="">Tất cả khu vực</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-slate-900 text-white px-5 py-2 rounded-xl text-sm font-bold hover:bg-slate-800 transition shadow-md">Lọc</button>
                @if(request()->has('search') || request('zone_id'))
                    <a href="{{ route('videos.index') }}" class="text-xs font-bold text-rose-500 hover:bg-rose-50 px-3 py-2 rounded-xl transition">Xóa lọc</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-400 uppercase text-[10px] font-bold border-b border-slate-100 bg-slate-50/30">
                        <tr>
                            <th class="text-left px-6 py-4">Tên Video</th>
                            <th class="text-left px-6 py-4">Khu vực</th>
                            <th class="text-center px-6 py-4">Kết quả AI</th>
                            <th class="text-center px-6 py-4">Thời gian</th>
                            <th class="text-right px-6 py-4">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="jobsTbody">
                        @forelse($processedVideos as $video)
                            <tr class="border-b border-slate-50 hover:bg-slate-50/80 transition-colors group">
                                <td class="px-6 py-4 font-bold text-slate-700 truncate max-w-[200px]">{{ $video->file_name }}</td>
                                <td class="px-6 py-4 font-bold text-indigo-500 text-xs uppercase tracking-tighter">{{ $video->zone->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center gap-2">
                                        <span class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-600 font-bold text-[10px]">A: {{ $video->count_direction_a }}</span>
                                        <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-600 font-bold text-[10px]">B: {{ $video->count_direction_b }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center text-slate-400 font-mono text-xs font-bold">{{ number_format($video->processing_time_ms / 1000, 2) }}s</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('violations.index', ['processed_video_id' => $video->id]) }}" class="px-3 py-1.5 rounded-xl bg-rose-50 text-rose-600 font-bold text-[10px] hover:bg-rose-100 transition">VI PHẠM</a>
                                        <button data-url="{{ $video->processed_video_url }}" class="btnView px-3 py-1.5 rounded-xl bg-slate-900 text-white font-bold text-[10px] hover:bg-slate-800 transition shadow-sm">
                                            XEM KẾT QUẢ
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-16 text-center text-slate-400 italic font-medium">Không tìm thấy dữ liệu video nào.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-5 border-t border-slate-50">
                {{ $processedVideos->links() }}
            </div>
        </div>
    </div>

<script>
    (() => {
        const API = "http://127.0.0.1:5000";
        const API_STATUS = (id) => `${API}/jobs/${id}`;
        const API_CREATE = `${API}/jobs`;

        const ZONES = @json($zonesForJs);

        // DOM Elements
        const videoInput = document.getElementById("videoInput");
        const btnPick = document.getElementById("btnPick");
        const btnRun = document.getElementById("btnRun");
        const localVideo = document.getElementById("localVideo");
        const localOverlay = document.getElementById("localOverlay");
        const resultVideo = document.getElementById("resultVideo");
        const uiBadge = document.getElementById("uiBadge");
        const zoneSelect = document.getElementById("zoneSelect");
        const zonesArea = document.getElementById("zones");
        const maxSpeedInput = document.getElementById("maxSpeed");
        const vidInfo = document.getElementById("vidInfo");

        let file = null;
        let currentBase = null;
        let pollTimer = null;

        // --- 1. LOGIC VẼ TỌA ĐỘ (CANVAS) ---

        function parseBaseFromRaw(raw) {
            if (!raw) return null;
            const m = String(raw).match(/#\s*base\s*=\s*(\d+)\s*x\s*(\d+)/i);
            return m ? { w: parseInt(m[1]), h: parseInt(m[2]) } : null;
        }

        function drawOverlayOnLocal() {
            if (!file || localVideo.classList.contains("hidden")) return;

            const polys = (() => {
                try { return JSON.parse(zonesArea.value || "[]"); } 
                catch { return []; }
            })();

            if (!polys.length || !localVideo.videoWidth) {
                localOverlay.classList.add("hidden");
                return;
            }

            localOverlay.classList.remove("hidden");
            const rect = localVideo.getBoundingClientRect();
            localOverlay.width = rect.width;
            localOverlay.height = rect.height;

            const ctx = localOverlay.getContext("2d");
            ctx.clearRect(0, 0, localOverlay.width, localOverlay.height);

            const vW = localVideo.videoWidth;
            const vH = localVideo.videoHeight;
            const bW = currentBase?.w || vW;
            const bH = currentBase?.h || vH;

            const scaleX = rect.width / bW;
            const scaleY = rect.height / bH;

            polys.forEach((poly, idx) => {
                if (!Array.isArray(poly) || poly.length < 2) return;
                
                ctx.beginPath();
                ctx.lineWidth = 3;
                ctx.setLineDash(idx === 0 ? [] : [5, 5]); // Nét đứt cho Zone B
                ctx.strokeStyle = idx === 0 ? "#10b981" : "#6366f1"; 
                ctx.fillStyle = idx === 0 ? "rgba(16, 185, 129, 0.15)" : "rgba(99, 102, 241, 0.15)";

                poly.forEach((pt, i) => {
                    const x = pt[0] * scaleX;
                    const y = pt[1] * scaleY;
                    if (i === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                });

                if (poly.length > 2) ctx.closePath();
                ctx.fill();
                ctx.stroke();

                // Nhãn vùng
                ctx.setLineDash([]);
                ctx.fillStyle = idx === 0 ? "#064e3b" : "#1e1b4b";
                ctx.font = "bold 11px Inter, sans-serif";
                ctx.fillText(idx === 0 ? "ZONE A" : "ZONE B", poly[0][0] * scaleX + 5, poly[0][1] * scaleY + 15);
            });
        }

        // --- 2. XỬ LÝ JOB VÀ POLLING ---

        async function pollJob(jobId) {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(async () => {
                try {
                    const res = await fetch(API_STATUS(jobId));
                    const j = await res.json();
                    if (!res.ok) throw new Error(j.error || "Lỗi server");

                    document.getElementById("uiStatus").textContent = j.status.toUpperCase();
                    const p = j.progress || 0;
                    document.getElementById("uiProgBar").style.width = p + "%";
                    document.getElementById("uiProgText").textContent = p + "%";
                    
                    if (j.status === "done") {
                        clearInterval(pollTimer);
                        document.getElementById("uiCount").textContent = `XONG (A:${j.count_zone_a || 0} | B:${j.count_zone_b || 0})`;
                        if (j.url) {
                            resultVideo.src = j.url;
                            resultVideo.load();
                            uiBadge.textContent = "KẾT QUẢ AI";
                        }
                    }
                } catch (e) {
                    clearInterval(pollTimer);
                    document.getElementById("uiErr").textContent = e.message;
                    document.getElementById("uiErr").classList.remove("hidden");
                }
            }, 1000);
        }

        // --- 3. SỰ KIỆN TƯƠNG TÁC ---

        zoneSelect.onchange = () => {
            const zone = ZONES.find(z => String(z.id) === String(zoneSelect.value));
            if (zone) {
                zonesArea.value = JSON.stringify(zone.polygons, null, 2);
                maxSpeedInput.value = zone.max_speed || "";
                currentBase = parseBaseFromRaw(zone.raw);
                
                const bPart = currentBase ? ` | Base: ${currentBase.w}x${currentBase.h}` : "";
                vidInfo.textContent = `Video: ${localVideo.videoWidth || '0'}x${localVideo.videoHeight || '0'}${bPart}`;
                
                // Hiển thị wrap tốc độ nếu là mode speeding
                const dt = document.getElementById("detectType").value;
                document.getElementById("maxSpeedWrap").classList.toggle("hidden", dt !== "speeding");
                
                drawOverlayOnLocal();
            }
        };

        btnPick.onclick = () => videoInput.click();
        videoInput.onchange = () => {
            file = videoInput.files[0];
            if (file) {
                const url = URL.createObjectURL(file);
                localVideo.src = url;
                localVideo.classList.remove("hidden");
                resultVideo.classList.add("hidden");
                uiBadge.textContent = "THIẾT LẬP VÙNG";
                document.getElementById("uiMsg").classList.add("hidden");
                
                localVideo.onloadedmetadata = () => {
                    vidInfo.textContent = `Video: ${localVideo.videoWidth}x${localVideo.videoHeight}`;
                    drawOverlayOnLocal();
                };
            }
        };

        btnRun.onclick = async () => {
            if (!file || !zoneSelect.value) return alert("Vui lòng chọn video và Zone trước!");

            console.log("Submitting job with:");
            
            btnRun.disabled = true;
            document.getElementById("uiErr").classList.add("hidden");
            document.getElementById("uiJob").textContent = "Đang khởi tạo...";

            const fd = new FormData();
            fd.append("video", file);
            fd.append("zone_id", zoneSelect.value);
            fd.append("detect_type", document.getElementById("detectType").value);
            fd.append("zones", zonesArea.value);
            fd.append("max_speed", maxSpeedInput.value);

            try {
                const res = await fetch(API_CREATE, { method: "POST", body: fd });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error);

                document.getElementById("uiJob").textContent = data.job_id.substring(0, 8);
                pollJob(data.job_id);
            } catch (e) {
                alert("Lỗi: " + e.message);
                btnRun.disabled = false;
            }
        };

        function attachViewEvents() {
            document.querySelectorAll(".btnView").forEach(btn => {
                btn.onclick = (e) => {
                    const url = btn.getAttribute("data-url");
                    if (!url) return alert("Video chưa sẵn sàng.");
                    
                    localVideo.classList.add("hidden");
                    localOverlay.classList.add("hidden");
                    
                    resultVideo.src = url;
                    resultVideo.classList.remove("hidden");
                    resultVideo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    resultVideo.load();
                    resultVideo.play();
                    uiBadge.textContent = "KẾT QUẢ AI";
                };
            });
        }

        window.addEventListener("resize", drawOverlayOnLocal);
        attachViewEvents();
    })();
</script>
@endsection