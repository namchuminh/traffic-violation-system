# app.py
import sys
sys.path.append("ultralytics")

import os, uuid, json, time, threading, shutil, subprocess, re, math
from collections import deque, Counter
from flask import Flask, request, jsonify, send_from_directory
from flask_cors import CORS
import cv2
import numpy as np
from ultralytics import YOLO

# =========================
# CONFIG
# =========================
HOST, PORT = "0.0.0.0", 5000
BASE = os.path.dirname(os.path.abspath(__file__))

UPLOAD = os.path.join(BASE, "uploads")
OUT = os.path.join(BASE, "out")
EVID = os.path.join(OUT, "evidence")
TMP = os.path.join(BASE, "tmp")
DB_FILE = os.path.join(BASE, "jobs_db.json")

os.makedirs(UPLOAD, exist_ok=True)
os.makedirs(OUT, exist_ok=True)
os.makedirs(EVID, exist_ok=True)
os.makedirs(TMP, exist_ok=True)

# MySQL config (theo cấu hình mày đưa)
MYSQL_HOST = os.getenv("MYSQL_HOST", "localhost")
MYSQL_PORT = int(os.getenv("MYSQL_PORT", "3306"))
MYSQL_USER = os.getenv("MYSQL_USER", "root")
MYSQL_PASS = os.getenv("MYSQL_PASS", "")
MYSQL_DB   = os.getenv("MYSQL_DB", "traffic_violation_system")

PUBLIC_BASE_URL = (os.getenv("PUBLIC_BASE_URL") or f"http://127.0.0.1:{PORT}").rstrip("/")

VEH = {"car", "motorcycle", "bus", "truck"}

app = Flask(__name__)
CORS(app)

model = YOLO("yolo26s.onnx")

lock = threading.Lock()
jobs = {}
history = []

# =========================
# MYSQL (pymysql or mysql-connector)
# =========================
_DB_LIB = None
try:
    import mysql.connector as _mysql_connector
    _DB_LIB = "mysql_connector"
except Exception:
    _mysql_connector = None

if _DB_LIB is None:
    try:
        import pymysql as _pymysql
        _DB_LIB = "pymysql"
    except Exception:
        _pymysql = None


def _db_connect():
    if _DB_LIB is None:
        raise RuntimeError("Missing MySQL driver. Install: pip install mysql-connector-python  (or)  pip install pymysql")
    if _DB_LIB == "mysql_connector":
        return _mysql_connector.connect(
            host=MYSQL_HOST, port=MYSQL_PORT,
            user=MYSQL_USER, password=MYSQL_PASS,
            database=MYSQL_DB, autocommit=False
        )
    return _pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT,
        user=MYSQL_USER, password=MYSQL_PASS,
        database=MYSQL_DB,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=_pymysql.cursors.DictCursor
    )

def db_update_realtime(pv_id, count_a, count_b):
    """Cập nhật số lượng đếm ngay trong lúc đang xử lý"""
    conn = _db_connect()
    try:
        if _DB_LIB == "mysql_connector":
            cur = conn.cursor()
            cur.execute(
                "UPDATE processed_videos SET count_direction_a=%s, count_direction_b=%s, updated_at=NOW() WHERE id=%s",
                (int(count_a), int(count_b), int(pv_id))
            )
            conn.commit()
            cur.close()
        else:
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE processed_videos SET count_direction_a=%s, count_direction_b=%s, updated_at=NOW() WHERE id=%s",
                    (int(count_a), int(count_b), int(pv_id))
                )
            conn.commit()
    finally:
        conn.close()

def db_insert_processed_video(*, file_name, processed_video_url, zone_id, count_a, count_b, processing_time_ms, processed_by):
    """
    processed_videos:
      id
      file_name
      processed_video_url
      zone_id
      count_direction_a
      count_direction_b
      processing_time_ms
      processed_by
      created_at
      updated_at
    """
    conn = _db_connect()
    try:
        if _DB_LIB == "mysql_connector":
            cur = conn.cursor()
            cur.execute(
                """
                INSERT INTO processed_videos
                  (file_name, processed_video_url, zone_id, count_direction_a, count_direction_b,
                   processing_time_ms, processed_by, created_at, updated_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s,NOW(),NOW())
                """,
                (file_name, processed_video_url, int(zone_id), int(count_a), int(count_b),
                 int(processing_time_ms), int(processed_by))
            )
            pid = cur.lastrowid
            conn.commit()
            cur.close()
            return int(pid)
        else:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    INSERT INTO processed_videos
                      (file_name, processed_video_url, zone_id, count_direction_a, count_direction_b,
                       processing_time_ms, processed_by, created_at, updated_at)
                    VALUES (%s,%s,%s,%s,%s,%s,%s,NOW(),NOW())
                    """,
                    (file_name, processed_video_url, int(zone_id), int(count_a), int(count_b),
                     int(processing_time_ms), int(processed_by))
                )
                pid = cur.lastrowid
            conn.commit()
            return int(pid)
    finally:
        try:
            conn.close()
        except:
            pass


def db_update_processed_video(*, processed_video_id, processed_video_url, count_a, count_b, processing_time_ms):
    """
    Update lại kết quả (schema giữ nguyên).
    """
    conn = _db_connect()
    try:
        if _DB_LIB == "mysql_connector":
            cur = conn.cursor()
            cur.execute(
                """
                UPDATE processed_videos
                SET processed_video_url=%s,
                    count_direction_a=%s,
                    count_direction_b=%s,
                    processing_time_ms=%s,
                    updated_at=NOW()
                WHERE id=%s
                """,
                (processed_video_url, int(count_a), int(count_b), int(processing_time_ms), int(processed_video_id))
            )
            conn.commit()
            cur.close()
        else:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    UPDATE processed_videos
                    SET processed_video_url=%s,
                        count_direction_a=%s,
                        count_direction_b=%s,
                        processing_time_ms=%s,
                        updated_at=NOW()
                    WHERE id=%s
                    """,
                    (processed_video_url, int(count_a), int(count_b), int(processing_time_ms), int(processed_video_id))
                )
            conn.commit()
    finally:
        try:
            conn.close()
        except:
            pass


def db_insert_violations(processed_video_id: int, rows: list):
    """
    violations:
      id
      violation_type
      evidence_image_url
      processed_video_id
      status
      handling_status
      created_at
      updated_at
    """
    if not rows:
        return 0
    conn = _db_connect()
    try:
        params = [
            (
                r.get("violation_type"),
                r.get("evidence_image_url"),
                int(processed_video_id),
                r.get("status") or "detected",
                r.get("handling_status") or "pending",
            )
            for r in rows
        ]
        if _DB_LIB == "mysql_connector":
            cur = conn.cursor()
            cur.executemany(
                """
                INSERT INTO violations
                  (violation_type, evidence_image_url, processed_video_id, status, handling_status, created_at, updated_at)
                VALUES (%s,%s,%s,%s,%s,NOW(),NOW())
                """,
                params
            )
            n = cur.rowcount
            conn.commit()
            cur.close()
            return int(n)
        else:
            with conn.cursor() as cur:
                cur.executemany(
                    """
                    INSERT INTO violations
                      (violation_type, evidence_image_url, processed_video_id, status, handling_status, created_at, updated_at)
                    VALUES (%s,%s,%s,%s,%s,NOW(),NOW())
                    """,
                    params
                )
                n = cur.rowcount
            conn.commit()
            return int(n)
    finally:
        try:
            conn.close()
        except:
            pass


def db_find_processed_video_id_by_job(job_id: str):
    like = f"%/{job_id}.mp4%"
    conn = _db_connect()
    try:
        if _DB_LIB == "mysql_connector":
            cur = conn.cursor()
            cur.execute(
                "SELECT id FROM processed_videos WHERE processed_video_url LIKE %s ORDER BY id DESC LIMIT 1",
                (like,)
            )
            row = cur.fetchone()
            cur.close()
            return int(row[0]) if row else None
        else:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT id FROM processed_videos WHERE processed_video_url LIKE %s ORDER BY id DESC LIMIT 1",
                    (like,)
                )
                row = cur.fetchone()
            return int(row["id"]) if row else None
    finally:
        try:
            conn.close()
        except:
            pass


def db_list_violations(processed_video_id: int):
    conn = _db_connect()
    try:
        if _DB_LIB == "mysql_connector":
            cur = conn.cursor()
            cur.execute(
                """
                SELECT id, violation_type, evidence_image_url, status, handling_status, created_at, updated_at
                FROM violations
                WHERE processed_video_id = %s
                ORDER BY id DESC
                """,
                (int(processed_video_id),)
            )
            rows = cur.fetchall() or []
            cur.close()
            out = []
            for r in rows:
                created_at = r[5]
                updated_at = r[6]
                out.append({
                    "id": int(r[0]),
                    "violation_type": r[1],
                    "evidence_image_url": r[2],
                    "status": r[3],
                    "handling_status": r[4],
                    "created_at": created_at.isoformat() if hasattr(created_at, "isoformat") else str(created_at),
                    "updated_at": updated_at.isoformat() if hasattr(updated_at, "isoformat") else str(updated_at),
                })
            return out
        else:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT id, violation_type, evidence_image_url, status, handling_status, created_at, updated_at
                    FROM violations
                    WHERE processed_video_id = %s
                    ORDER BY id DESC
                    """,
                    (int(processed_video_id),)
                )
                rows = cur.fetchall() or []
            for r in rows:
                ca = r.get("created_at")
                ua = r.get("updated_at")
                if hasattr(ca, "isoformat"):
                    r["created_at"] = ca.isoformat()
                if hasattr(ua, "isoformat"):
                    r["updated_at"] = ua.isoformat()
            return rows
    finally:
        try:
            conn.close()
        except:
            pass


# =========================
# persistence (history only)
# =========================
def load_db():
    global history
    if os.path.exists(DB_FILE):
        try:
            with open(DB_FILE, "r", encoding="utf-8") as f:
                history = json.load(f) or []
        except:
            history = []
    else:
        history = []


def save_db():
    tmp = DB_FILE + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(history, f, ensure_ascii=False, indent=2)
    os.replace(tmp, DB_FILE)


# =========================
# ffmpeg
# =========================
def ffmpeg_path():
    p = (os.getenv("FFMPEG_PATH") or "").strip()
    if p and os.path.isfile(p):
        return p
    local = os.path.join(BASE, "ffmpeg", "bin", "ffmpeg.exe")  # Windows portable
    if os.path.isfile(local):
        return local
    return shutil.which("ffmpeg")


def run_ffmpeg(in_avi, out_mp4):
    ff = ffmpeg_path()
    if not ff:
        raise RuntimeError(
            "ffmpeg not found. Fix: đặt ffmpeg.exe vào be/ffmpeg/bin/ffmpeg.exe "
            "hoặc set FFMPEG_PATH hoặc add PATH."
        )

    p = subprocess.run(
        [ff, "-y", "-i", in_avi, "-an",
         "-c:v", "libx264", "-preset", "veryfast", "-crf", "23",
         "-pix_fmt", "yuv420p", "-movflags", "+faststart",
         out_mp4],
        capture_output=True, text=True
    )
    if p.returncode != 0:
        tail = (p.stderr or "")[-2000:]
        raise RuntimeError("ffmpeg failed:\n" + (tail if tail else "no stderr"))


# =========================
# zones parser
# =========================
def _parse_base(s: str):
    m = re.search(r"base\s*=\s*(\d+)\s*x\s*(\d+)", s or "", flags=re.I)
    if not m:
        return None
    return int(m.group(1)), int(m.group(2))


def parse_zones_text(s: str):
    """
    Input:
      - JSON: [[[x,y],...], [[x,y],...]] hoặc [[x,y],...]
      - Roboflow: [ np.array([[...]]), ... ]
    Optional header: #base=1280x720
    Note: cho phép "line" 2 điểm (dùng cho red_light).
    """
    s = (s or "").strip()
    if not s:
        raise ValueError("zones empty")

    base = _parse_base(s)

    # JSON first
    try:
        obj = json.loads(s)
        if isinstance(obj, list) and len(obj) >= 1 and isinstance(obj[0], list):
            polys = []

            # single poly/line: [[x,y],...]
            if isinstance(obj[0], (list, tuple)) and len(obj[0]) == 2 and all(
                isinstance(p, (list, tuple)) and len(p) == 2 for p in obj
            ):
                pts = [(float(p[0]), float(p[1])) for p in obj]
                polys.append(pts)
                return polys, base

            # list of polys/lines
            for poly in obj:
                if not isinstance(poly, list) or len(poly) < 2:
                    continue
                if not all(isinstance(p, (list, tuple)) and len(p) == 2 for p in poly):
                    continue
                pts = [(float(p[0]), float(p[1])) for p in poly]
                polys.append(pts)

            if len(polys) >= 1:
                return polys, base
    except:
        pass

    # np.array([[...]])
    matches = re.findall(r"np\.array\(\s*(\[\[.*?\]\])\s*\)", s, flags=re.DOTALL)
    if len(matches) < 1:
        raise ValueError("cannot parse zones. Paste đúng JSON hoặc block np.array([[...]]).")

    polys = []
    for m in matches:
        pts_raw = json.loads(m)
        if not isinstance(pts_raw, list) or len(pts_raw) < 2:
            continue
        pts = [(float(p[0]), float(p[1])) for p in pts_raw]
        polys.append(pts)

    if len(polys) < 1:
        raise ValueError("need >=1 poly/line")
    return polys, base


def scale_poly(poly, from_w, from_h, to_w, to_h):
    sx = to_w / from_w
    sy = to_h / from_h
    return [(x * sx, y * sy) for x, y in poly]


def to_contour(poly):
    if len(poly) < 3:
        raise ValueError("contour requires >=3 points")
    arr = np.array([[int(x), int(y)] for x, y in poly], dtype=np.int32)
    return arr.reshape((-1, 1, 2))


def point_in_poly(cnt, x, y):
    return cv2.pointPolygonTest(cnt, (float(x), float(y)), False) >= 0


def label_anchor(cnt):
    x, y, w, h = cv2.boundingRect(cnt)
    return x + 8, y + 28


def draw_zone(frame, cnt, color, title, text):
    overlay = frame.copy()
    cv2.fillPoly(overlay, [cnt], color)
    cv2.addWeighted(overlay, 0.18, frame, 0.82, 0, frame)
    cv2.polylines(frame, [cnt], True, color, 2)

    ax, ay = label_anchor(cnt)
    cv2.putText(frame, f"{title} {text}", (ax, ay),
                cv2.FONT_HERSHEY_SIMPLEX, 0.85, color, 2, cv2.LINE_AA)


def fmt_duration(sec):
    sec = int(sec)
    return f"{sec//60:02d}:{sec%60:02d}"


def is_normalized(poly):
    return all(0.0 <= x <= 1.0 and 0.0 <= y <= 1.0 for x, y in poly)


def parse_int(v, default=None, minv=None, maxv=None):
    try:
        x = int(str(v).strip())
        if minv is not None and x < minv:
            x = minv
        if maxv is not None and x > maxv:
            x = maxv
        return x
    except:
        return default


def parse_float(v, default=None, minv=None, maxv=None):
    try:
        x = float(str(v).strip())
        if minv is not None and x < minv:
            x = minv
        if maxv is not None and x > maxv:
            x = maxv
        return x
    except:
        return default


def default_ppm(W, H):
    diag = math.sqrt(W * W + H * H)
    return max(5.0, diag / 50.0)


# =========================
# common utils
# =========================
def _cls_name(names, cl: int):
    try:
        cl = int(cl)
    except:
        return str(cl)
    if isinstance(names, dict):
        return str(names.get(cl, cl))
    if isinstance(names, (list, tuple)) and 0 <= cl < len(names):
        return str(names[cl])
    return str(cl)


def _traffic_light_class_ids(names):
    ids = []
    if isinstance(names, dict):
        items = names.items()
    elif isinstance(names, (list, tuple)):
        items = enumerate(names)
    else:
        return ids
    for i, n in items:
        s = str(n).lower()
        if "traffic" in s and "light" in s:
            ids.append(int(i))
    return set(ids)


# =========================
# draw text with background
# =========================
def put_text_bg(img, text, org, font=cv2.FONT_HERSHEY_SIMPLEX, font_scale=0.65,
                text_color=(255, 255, 255), bg_color=(0, 0, 0), thickness=2, pad=4):
    H, W = img.shape[:2]
    x, y = int(org[0]), int(org[1])
    (tw, th), bl = cv2.getTextSize(text, font, font_scale, thickness)

    x1 = max(0, x)
    y1 = max(0, y - th - bl - pad * 2)
    x2 = min(W - 1, x + tw + pad * 2)
    y2 = min(H - 1, y + pad)

    cv2.rectangle(img, (x1, y1), (x2, y2), bg_color, -1)
    tx = x1 + pad
    ty = min(H - 2, y - pad)
    cv2.putText(img, text, (tx, ty), font, font_scale, text_color, thickness, cv2.LINE_AA)


def _light_vi_text(state: str) -> str:
    s = (state or "unknown").lower()
    if s == "red":
        return "DO"
    if s == "yellow":
        return "VANG"
    if s == "green":
        return "XANH"
    return "KHONG RO"


def _light_color_bgr(state: str):
    s = (state or "unknown").lower()
    if s == "red":
        return (0, 0, 255)
    if s == "yellow":
        return (0, 255, 255)
    if s == "green":
        return (0, 255, 0)
    return (255, 255, 255)


def _draw_tl_box_and_label(frame, bbox_xyxy, state: str):
    if bbox_xyxy is None:
        return
    x1, y1, x2, y2 = map(int, bbox_xyxy)
    col = _light_color_bgr(state)
    cv2.rectangle(frame, (x1, y1), (x2, y2), col, 2, cv2.LINE_AA)
    label = f"DEN: {_light_vi_text(state)}"
    put_text_bg(frame, label, (x1, max(18, y1 - 2)),
                font_scale=0.70, text_color=(255, 255, 255),
                bg_color=(0, 0, 0), thickness=2)


# =========================
# red-light: ROI crop / classification
# =========================
def _crop_pad(frame, x1, y1, x2, y2, pad=0.15):
    H, W = frame.shape[:2]
    w = max(1, int(x2 - x1))
    h = max(1, int(y2 - y1))
    px = int(w * pad)
    py = int(h * pad)
    xa = max(0, int(x1) - px)
    ya = max(0, int(y1) - py)
    xb = min(W, int(x2) + px)
    yb = min(H, int(y2) + py)
    if xb <= xa or yb <= ya:
        return None
    return frame[ya:yb, xa:xb]


def crop_by_polygon(frame, cnt):
    H, W = frame.shape[:2]
    x, y, w, h = cv2.boundingRect(cnt)
    x2 = min(W, x + w)
    y2 = min(H, y + h)
    if w <= 1 or h <= 1:
        return None

    roi = frame[y:y2, x:x2].copy()
    mask = np.zeros((roi.shape[0], roi.shape[1]), dtype=np.uint8)

    cnt_shift = cnt.copy()
    cnt_shift[:, :, 0] -= x
    cnt_shift[:, :, 1] -= y
    cv2.fillPoly(mask, [cnt_shift], 255)

    return cv2.bitwise_and(roi, roi, mask=mask)


def _hue_dist(a, b):
    d = abs(int(a) - int(b))
    return min(d, 180 - d)


def _median_hue(h_u8, mask_u8):
    pts = h_u8[mask_u8 > 0]
    if pts.size == 0:
        return None
    return int(np.median(pts))


def classify_traffic_light_color(roi_bgr):
    if roi_bgr is None or roi_bgr.size == 0:
        return "unknown"
    roi = roi_bgr
    if roi.shape[0] < 10 or roi.shape[1] < 10:
        return "unknown"

    roi = cv2.GaussianBlur(roi, (5, 5), 0)
    hsv = cv2.cvtColor(roi, cv2.COLOR_BGR2HSV)
    Hc, Sc, Vc = cv2.split(hsv)

    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    Vc = clahe.apply(Vc)

    Ef = (Vc.astype(np.float32) * (Sc.astype(np.float32) / 255.0))
    h, w = Vc.shape[:2]
    if h * w < 200:
        return "unknown"

    e90 = float(np.percentile(Ef, 90))
    e_thr = max(18.0, e90)
    bright = (Ef >= e_thr).astype(np.uint8) * 255

    if cv2.countNonZero(bright) < max(10, int(h * w * 0.002)):
        return "unknown"

    vertical = (h >= int(w * 1.15))
    horizontal = (w >= int(h * 1.15))

    def band_score(y1, y2, x1, x2):
        E = Ef[y1:y2, x1:x2]
        M = bright[y1:y2, x1:x2]
        if E.size == 0:
            return 0.0
        vals = E[M > 0]
        if vals.size == 0:
            return 0.0
        return float(vals.sum())

    if vertical:
        t1 = h // 3
        t2 = 2 * h // 3
        s0 = band_score(0, t1, 0, w)
        s1 = band_score(t1, t2, 0, w)
        s2 = band_score(t2, h, 0, w)
        bands = [s0, s1, s2]
        idx = int(np.argmax(bands))
        mapped = ["red", "yellow", "green"][idx]
        second = float(sorted(bands, reverse=True)[1])
        ratio = (bands[idx] + 1e-6) / (second + 1e-6)

        if idx == 0:
            y1, y2, x1, x2 = 0, t1, 0, w
            target_h = 0
        elif idx == 1:
            y1, y2, x1, x2 = t1, t2, 0, w
            target_h = 25
        else:
            y1, y2, x1, x2 = t2, h, 0, w
            target_h = 60

        sub_bright = bright[y1:y2, x1:x2]
        med_h = _median_hue(Hc[y1:y2, x1:x2], sub_bright)
        if med_h is None:
            return "unknown"

        if _hue_dist(med_h, target_h) > 28 and ratio < 1.8:
            return "unknown"
        if ratio < 1.35:
            return "unknown"
        return mapped

    if horizontal:
        t1 = w // 3
        t2 = 2 * w // 3
        s0 = band_score(0, h, 0, t1)
        s1 = band_score(0, h, t1, t2)
        s2 = band_score(0, h, t2, w)
        bands = [s0, s1, s2]
        idx = int(np.argmax(bands))
        mapped = ["red", "yellow", "green"][idx]
        second = float(sorted(bands, reverse=True)[1])
        ratio = (bands[idx] + 1e-6) / (second + 1e-6)

        if idx == 0:
            y1, y2, x1, x2 = 0, h, 0, t1
            target_h = 0
        elif idx == 1:
            y1, y2, x1, x2 = 0, h, t1, t2
            target_h = 25
        else:
            y1, y2, x1, x2 = 0, h, t2, w
            target_h = 60

        sub_bright = bright[y1:y2, x1:x2]
        med_h = _median_hue(Hc[y1:y2, x1:x2], sub_bright)
        if med_h is None:
            return "unknown"

        if _hue_dist(med_h, target_h) > 28 and ratio < 1.8:
            return "unknown"
        if ratio < 1.35:
            return "unknown"
        return mapped

    Hpts = Hc[bright > 0]
    Spts = Sc[bright > 0]
    Vpts = Vc[bright > 0]
    if Hpts.size < 10:
        return "unknown"

    v70 = float(np.percentile(Vpts, 70))
    keep = (Spts >= 80) & (Vpts >= int(max(150, v70)))
    Hpts = Hpts[keep]
    if Hpts.size < 8:
        return "unknown"

    med_h = int(np.median(Hpts))
    d_red = min(_hue_dist(med_h, 0), _hue_dist(med_h, 179))
    d_yel = _hue_dist(med_h, 25)
    d_grn = _hue_dist(med_h, 60)

    dmin = min(d_red, d_yel, d_grn)
    if dmin > 22:
        return "unknown"
    if dmin == d_red:
        return "red"
    if dmin == d_grn:
        return "green"
    return "yellow"


def classify_light_from_polygon(frame, cnt_tl):
    roi = crop_by_polygon(frame, cnt_tl)
    if roi is None:
        return "unknown"
    return classify_traffic_light_color(roi)


# =========================
# stopline geometry
# =========================
def _poly_to_line(poly):
    if len(poly) == 2:
        return (float(poly[0][0]), float(poly[0][1])), (float(poly[1][0]), float(poly[1][1]))

    pts = [(float(x), float(y)) for x, y in poly]
    best = (pts[0], pts[1])
    best_d = -1.0
    for i in range(len(pts)):
        for j in range(i + 1, len(pts)):
            dx = pts[i][0] - pts[j][0]
            dy = pts[i][1] - pts[j][1]
            d = dx * dx + dy * dy
            if d > best_d:
                best_d = d
                best = (pts[i], pts[j])
    return best


def _side_of_line(A, B, P):
    ax, ay = A
    bx, by = B
    px, py = P
    return (bx - ax) * (py - ay) - (by - ay) * (px - ax)


def _dist_point_to_segment(A, B, P):
    ax, ay = A
    bx, by = B
    px, py = P
    vx = bx - ax
    vy = by - ay
    wx = px - ax
    wy = py - ay
    vv = vx * vx + vy * vy
    if vv <= 1e-9:
        return math.hypot(px - ax, py - ay), 0.0
    t = (wx * vx + wy * vy) / vv
    t_clamp = max(0.0, min(1.0, t))
    cx = ax + t_clamp * vx
    cy = ay + t_clamp * vy
    return math.hypot(px - cx, py - cy), t_clamp


def _draw_stopline(frame, A, B, color=(0, 0, 255), thickness=4):
    cv2.line(frame, (int(A[0]), int(A[1])), (int(B[0]), int(B[1])), color, thickness, cv2.LINE_AA)
    mx = int((A[0] + B[0]) / 2)
    my = int((A[1] + B[1]) / 2)
    cv2.putText(frame, "VACH DUNG", (mx + 8, my - 8),
                cv2.FONT_HERSHEY_SIMPLEX, 0.8, color, 2, cv2.LINE_AA)


# =========================
# evidence helper
# =========================
def save_evidence_image(job_id: str, frame_bgr, tag: str):
    job_dir = os.path.join(EVID, job_id)
    os.makedirs(job_dir, exist_ok=True)
    fn = f"{tag}_{uuid.uuid4().hex[:8]}.jpg"
    path = os.path.join(job_dir, fn)
    cv2.imwrite(path, frame_bgr)
    return f"{PUBLIC_BASE_URL}/evidence/{job_id}/{fn}"


def _save_violation_now(job_id: str, processed_video_id: int, violation_type: str, frame_bgr, tag: str):
    """
    Insert violation ngay lập tức + cập nhật counter trong memory.
    """
    evid = save_evidence_image(job_id, frame_bgr, tag)
    n = db_insert_violations(int(processed_video_id), [{
        "violation_type": violation_type,
        "evidence_image_url": evid,
        "status": "detected",
        "handling_status": "pending",
    }])
    with lock:
        jobs[job_id]["violations_saved"] = int(jobs[job_id].get("violations_saved", 0)) + int(n)
    return evid


# =========================
# worker
# =========================
def worker(job_id: str):
    try:
        with lock:
            job = jobs[job_id]
            job["status"] = "processing"
            job["progress"] = 0
            job["error"] = None
            job["db_error"] = None

        in_path = job["in_path"]
        filename = job["filename"]
        detect_type = job.get("detect_type", "count")
        max_speed = job.get("max_speed")
        ppm = job.get("ppm")
        zone_id = job.get("zone_id")
        processed_by = job.get("processed_by", 1)
        processed_video_id = job.get("processed_video_id")  # đã insert ngay từ create_job

        cap = cv2.VideoCapture(in_path)
        if not cap.isOpened():
            with lock:
                jobs[job_id].update({"status": "error", "error": "cannot open video"})
            return

        fps = cap.get(cv2.CAP_PROP_FPS) or 25
        W = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH) or 1280)
        H = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT) or 720)
        total = int(cap.get(cv2.CAP_PROP_FRAME_COUNT) or 0)
        cap.release()

        tmp_avi = os.path.join(TMP, f"{job_id}.avi")
        out_mp4 = os.path.join(OUT, f"{job_id}.mp4")

        writer = cv2.VideoWriter(tmp_avi, cv2.VideoWriter_fourcc(*"MJPG"), fps, (W, H))
        if not writer.isOpened():
            with lock:
                jobs[job_id].update({"status": "error", "error": "cannot open writer (AVI MJPG)"})
            return

        names = getattr(model, "names", {}) or {}
        TL_IDS = _traffic_light_class_ids(names)

        frame_idx = 0
        t0 = time.time()
        polys = job.get("polys", [])

        # output counters (for processed_videos schema)
        count_dir_a = 0
        count_dir_b = 0

        # =========================
        # COUNT
        # =========================
        if detect_type == "count":
            cntA = to_contour(polys[0])
            hasB = len(polys) >= 2 and len(polys[1]) >= 3
            cntB = to_contour(polys[1]) if hasB else None

            countA = 0
            countB = 0

            inA_state = {}
            inB_state = {}
            outA = {}
            outB = {}
            OUT_THRESH = int(fps * 0.5)

            inA_now_ids = set()
            inB_now_ids = set()

            for r in model.track(
                source=in_path,
                stream=True,
                persist=True,
                tracker="bytetrack.yaml",
                conf=0.25,
                imgsz=640,
                verbose=False
            ):
                frame = r.orig_img
                if frame is None:
                    continue

                frame_idx += 1
                if total > 0:
                    with lock:
                        jobs[job_id]["progress"] = min(95, int(frame_idx * 95 / total))

                inA_now_ids.clear()
                inB_now_ids.clear()

                boxes = r.boxes
                if boxes is not None and len(boxes) and boxes.id is not None:
                    xyxy = boxes.xyxy.cpu().numpy()
                    cls = boxes.cls.cpu().numpy().astype(int)
                    confs = boxes.conf.cpu().numpy()
                    ids = boxes.id.cpu().numpy().astype(int)

                    for (x1, y1, x2, y2), cf, cl, tid in zip(xyxy, confs, cls, ids):
                        cname = _cls_name(names, int(cl))
                        if cname not in VEH:
                            continue

                        tid = int(tid)
                        cx = float((x1 + x2) / 2.0)
                        cy = float(y2)

                        raw_inA = point_in_poly(cntA, cx, cy)
                        raw_inB = False
                        if hasB and cntB is not None:
                            raw_inB = point_in_poly(cntB, cx, cy)
                            if raw_inA and raw_inB:
                                raw_inB = False

                        if raw_inA:
                            inA_now_ids.add(tid)
                        if raw_inB:
                            inB_now_ids.add(tid)

                        prevA = inA_state.get(tid, False)
                        if raw_inA:
                            outA[tid] = 0
                            if not prevA:
                                countA += 1
                            inA_state[tid] = True
                        else:
                            outA[tid] = outA.get(tid, 0) + 1
                            if outA[tid] > OUT_THRESH:
                                inA_state[tid] = False

                        if hasB:
                            prevB = inB_state.get(tid, False)
                            if raw_inB:
                                outB[tid] = 0
                                if not prevB:
                                    countB += 1
                                inB_state[tid] = True
                            else:
                                outB[tid] = outB.get(tid, 0) + 1
                                if outB[tid] > OUT_THRESH:
                                    inB_state[tid] = False

                        x1i, y1i, x2i, y2i = map(int, [x1, y1, x2, y2])
                        cv2.rectangle(frame, (x1i, y1i), (x2i, y2i), (0, 255, 0), 2)
                        cv2.circle(frame, (int(cx), int(cy)), 4, (0, 255, 0), -1)

                draw_zone(frame, cntA, (0, 255, 255), "CHIEU A:", f"{countA}")
                if hasB and cntB is not None:
                    draw_zone(frame, cntB, (255, 0, 255), "CHIEU B:", f"{countB}")

                writer.write(frame)

                # Cập nhật vào bộ nhớ RAM để Frontend lấy qua API GET /jobs/<id>
                with lock:
                    jobs[job_id]["progress"] = min(95, int(frame_idx * 95 / total)) if total > 0 else 0
                    jobs[job_id]["count_zone_a"] = int(countA)
                    jobs[job_id]["count_zone_b"] = int(countB)

                # CẬP NHẬT CSDL NGAY LẬP TỨC (Real-time)
                # Để tránh làm chậm hệ thống, cứ 30 frames (~1 giây video) ta update DB 1 lần
                if frame_idx % 30 == 0 and processed_video_id:
                    try:
                        db_update_realtime(processed_video_id, countA, countB)
                    except Exception as e:
                        print(f"Lỗi update DB real-time: {e}")

            count_dir_a = int(countA)
            count_dir_b = int(countB)

        # =========================
        # SPEEDING
        # =========================
        elif detect_type == "speeding":
            cntS = to_contour(polys[0])

            max_speed_kmh = int(max_speed if max_speed is not None else 60)
            ppm_v = float(ppm if ppm is not None else default_ppm(W, H))

            prev_pos = {}
            prev_frame = {}
            over_state = {}
            out_frames = {}
            OUT_THRESH = int(fps * 0.7)

            over_events = 0
            in_now_ids = set()

            for r in model.track(
                source=in_path,
                stream=True,
                persist=True,
                tracker="bytetrack.yaml",
                conf=0.25,
                imgsz=640,
                verbose=False
            ):
                frame = r.orig_img
                if frame is None:
                    continue

                frame_idx += 1
                if total > 0:
                    with lock:
                        jobs[job_id]["progress"] = min(95, int(frame_idx * 95 / total))

                in_now_ids.clear()

                boxes = r.boxes
                if boxes is not None and len(boxes) and boxes.id is not None:
                    xyxy = boxes.xyxy.cpu().numpy()
                    cls = boxes.cls.cpu().numpy().astype(int)
                    confs = boxes.conf.cpu().numpy()
                    ids = boxes.id.cpu().numpy().astype(int)

                    for (x1, y1, x2, y2), cf, cl, tid in zip(xyxy, confs, cls, ids):
                        cname = _cls_name(names, int(cl))
                        if cname not in VEH:
                            continue

                        cx = float((x1 + x2) / 2.0)
                        cy = float(y2)
                        tid = int(tid)

                        inside = point_in_poly(cntS, cx, cy)
                        if inside:
                            in_now_ids.add(tid)
                            out_frames[tid] = 0
                        else:
                            out_frames[tid] = out_frames.get(tid, 0) + 1
                            if out_frames[tid] > OUT_THRESH:
                                over_state[tid] = False
                            prev_pos[tid] = (cx, cy)
                            prev_frame[tid] = frame_idx

                        speed_kmh = None
                        if inside:
                            pf = prev_frame.get(tid)
                            pp = prev_pos.get(tid)
                            if pf is not None and pp is not None:
                                dt = (frame_idx - pf) / float(fps)
                                if dt > 0:
                                    dx = cx - pp[0]
                                    dy = cy - pp[1]
                                    dist_px = math.sqrt(dx * dx + dy * dy)
                                    speed_mps = (dist_px / ppm_v) / dt
                                    speed_kmh = speed_mps * 3.6
                            prev_pos[tid] = (cx, cy)
                            prev_frame[tid] = frame_idx

                        is_over = inside and (speed_kmh is not None) and (speed_kmh > max_speed_kmh)

                        trigger_over = False
                        if is_over and not over_state.get(tid, False):
                            over_events += 1
                            over_state[tid] = True
                            trigger_over = True

                        x1i, y1i, x2i, y2i = map(int, [x1, y1, x2, y2])
                        color = (0, 0, 255) if is_over else (0, 255, 0)
                        cv2.rectangle(frame, (x1i, y1i), (x2i, y2i), color, 2)
                        cv2.circle(frame, (int(cx), int(cy)), 4, color, -1)

                        sp = "--"
                        if speed_kmh is not None and inside:
                            sp = f"{speed_kmh:.1f}km/h"
                        tag = "IN" if inside else "-"
                        cv2.putText(frame, f"{cname}#{tid} {int(cf * 100)}% {tag} {sp}",
                                    (x1i, max(20, y1i - 8)),
                                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2, cv2.LINE_AA)

                        # lưu violation ngay khi vượt ngưỡng (1 lần / track)
                        if trigger_over and processed_video_id:
                            try:
                                _save_violation_now(job_id, processed_video_id, "speeding", frame, f"speed_{tid}")
                            except Exception as e:
                                with lock:
                                    jobs[job_id]["db_error"] = str(e)

                draw_zone(frame, cntS, (255, 255, 0), "SPEED",
                          f"LIMIT:{max_speed_kmh} IN:{len(in_now_ids)} OVER:{over_events}")

                writer.write(frame)

                with lock:
                    jobs[job_id]["inA"] = int(len(in_now_ids))
                    jobs[job_id]["inB"] = 0
                    jobs[job_id]["over_speed"] = int(over_events)
                    jobs[job_id]["max_speed"] = int(max_speed_kmh)
                    jobs[job_id]["ppm"] = float(ppm_v)

            count_dir_a = 0
            count_dir_b = 0

        # =========================
        # RED LIGHT (ĐỎ + VÀNG)
        # =========================
        elif detect_type == "red_light":
            stop_poly = polys[0]
            A, B = _poly_to_line(stop_poly)

            has_tl_roi = (len(polys) >= 2 and len(polys[1]) >= 3)
            cntTL = to_contour(polys[1]) if has_tl_roi else None

            dist_thresh = max(8.0, 0.012 * max(W, H))
            side_eps = 1e-6

            state_hist = deque(maxlen=12)
            stable_state = "unknown"

            last_side = {}
            violated = {}      # tid -> "red" | "yellow"
            cooldown = {}
            CD_FR = int(fps * 1.0)

            last_seen = {}
            FORGET_FR = int(fps * 4.0)

            viol_count = 0
            viol_red = 0
            viol_yellow = 0
            near_now = set()

            def _update_stable(new_state):
                nonlocal stable_state
                if new_state:
                    state_hist.append(new_state)
                if len(state_hist) == 0:
                    stable_state = "unknown"
                    return stable_state
                cnt = Counter([s for s in state_hist if s != "unknown"])
                stable_state = "unknown" if len(cnt) == 0 else cnt.most_common(1)[0][0]
                return stable_state

            for r in model.track(
                source=in_path,
                stream=True,
                persist=True,
                tracker="bytetrack.yaml",
                conf=0.25,
                imgsz=640,
                verbose=False
            ):
                frame = r.orig_img
                if frame is None:
                    continue

                frame_idx += 1
                if total > 0:
                    with lock:
                        jobs[job_id]["progress"] = min(95, int(frame_idx * 95 / total))

                boxes = r.boxes
                tl_bbox = None

                if has_tl_roi and cntTL is not None:
                    x, y, w, h = cv2.boundingRect(cntTL)
                    tl_bbox = (x, y, x + w, y + h)
                    new_state = classify_light_from_polygon(frame, cntTL)
                else:
                    best_tl = None
                    if boxes is not None and len(boxes):
                        xyxy_all = boxes.xyxy.cpu().numpy()
                        cls_all = boxes.cls.cpu().numpy().astype(int)
                        conf_all = boxes.conf.cpu().numpy()
                        for (x1, y1, x2, y2), cf, cl in zip(xyxy_all, conf_all, cls_all):
                            if TL_IDS and int(cl) not in TL_IDS:
                                continue
                            if best_tl is None or float(cf) > best_tl[0]:
                                best_tl = (float(cf), float(x1), float(y1), float(x2), float(y2))

                    if best_tl is not None:
                        _, x1, y1, x2, y2 = best_tl
                        tl_bbox = (x1, y1, x2, y2)
                        roi = _crop_pad(frame, x1, y1, x2, y2, pad=0.2)
                        new_state = classify_traffic_light_color(roi)
                    else:
                        new_state = "unknown"

                _update_stable(new_state)

                if frame_idx % int(max(1, fps)) == 0:
                    dead = [tid for tid, ls in last_seen.items() if (frame_idx - ls) > FORGET_FR]
                    for tid in dead:
                        last_seen.pop(tid, None)
                        last_side.pop(tid, None)
                        cooldown.pop(tid, None)
                        violated.pop(tid, None)

                near_now.clear()
                if boxes is not None and len(boxes) and boxes.id is not None:
                    xyxy = boxes.xyxy.cpu().numpy()
                    cls = boxes.cls.cpu().numpy().astype(int)
                    confs = boxes.conf.cpu().numpy()
                    ids = boxes.id.cpu().numpy().astype(int)

                    for (x1, y1, x2, y2), cf, cl, tid in zip(xyxy, confs, cls, ids):
                        cname = _cls_name(names, int(cl))
                        if cname not in VEH:
                            continue

                        tid = int(tid)
                        last_seen[tid] = frame_idx

                        cx = float((x1 + x2) / 2.0)
                        cy = float(y2)

                        if cooldown.get(tid, 0) > 0:
                            cooldown[tid] -= 1

                        s = _side_of_line(A, B, (cx, cy))
                        prevs = last_side.get(tid)

                        d, tproj = _dist_point_to_segment(A, B, (cx, cy))
                        near_line = (d <= dist_thresh and 0.05 <= tproj <= 0.95)
                        if near_line:
                            near_now.add(tid)

                        crossed = False
                        if prevs is None:
                            last_side[tid] = s
                        else:
                            if near_line and ((prevs > side_eps and s < -side_eps) or (prevs < -side_eps and s > side_eps)):
                                crossed = True
                                last_side[tid] = s
                            else:
                                if abs(s) > side_eps:
                                    last_side[tid] = s

                        new_violation_state = None
                        if stable_state in ("red", "yellow") and crossed and (tid not in violated) and cooldown.get(tid, 0) == 0:
                            viol_count += 1
                            if stable_state == "red":
                                viol_red += 1
                            else:
                                viol_yellow += 1
                            violated[tid] = stable_state
                            cooldown[tid] = CD_FR
                            new_violation_state = stable_state

                        x1i, y1i, x2i, y2i = map(int, [x1, y1, x2, y2])
                        v_state = violated.get(tid)  # None | "red" | "yellow"
                        is_v = v_state is not None

                        if not is_v:
                            color = (0, 255, 0)
                        else:
                            color = (0, 0, 255) if v_state == "red" else (0, 255, 255)

                        cv2.rectangle(frame, (x1i, y1i), (x2i, y2i), color, 2)
                        cv2.circle(frame, (int(cx), int(cy)), 4, color, -1)

                        put_text_bg(
                            frame, cname, (x1i, max(20, y1i - 6)),
                            font_scale=0.65,
                            text_color=(255, 255, 255),
                            bg_color=(0, 128, 0) if not is_v else ((0, 0, 180) if v_state == "red" else (0, 120, 120)),
                            thickness=2
                        )

                        if is_v:
                            msg = "VUOT DEN DO" if v_state == "red" else "VUOT DEN VANG"
                            bg = (0, 0, 255) if v_state == "red" else (0, 255, 255)
                            put_text_bg(frame, msg, (x1i, min(H - 6, y2i + 28)),
                                        font_scale=0.70,
                                        text_color=(0, 0, 0) if v_state == "yellow" else (255, 255, 255),
                                        bg_color=bg,
                                        thickness=2)

                        # lưu violation ngay tại frame vừa phạm
                        if new_violation_state in ("red", "yellow") and processed_video_id:
                            try:
                                vtype = "red_light" if new_violation_state == "red" else "yellow_light"
                                _save_violation_now(job_id, processed_video_id, vtype, frame, f"rl_{new_violation_state}_{tid}")
                            except Exception as e:
                                with lock:
                                    jobs[job_id]["db_error"] = str(e)

                _draw_stopline(frame, A, B, color=(0, 0, 255), thickness=4)
                _draw_tl_box_and_label(frame, tl_bbox, stable_state)

                hud = f"DEN:{_light_vi_text(stable_state)} | VUOT_DO:{viol_red} | VUOT_VANG:{viol_yellow} | TONG:{viol_count}"
                cv2.putText(frame, hud, (20, 40),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.9, (255, 255, 255), 2, cv2.LINE_AA)

                writer.write(frame)

                with lock:
                    jobs[job_id]["inA"] = int(len(near_now))
                    jobs[job_id]["inB"] = 0
                    jobs[job_id]["red_light_state"] = stable_state
                    jobs[job_id]["red_light_violations"] = int(viol_count)
                    jobs[job_id]["red_light_violations_red"] = int(viol_red)
                    jobs[job_id]["yellow_light_violations"] = int(viol_yellow)

            count_dir_a = 0
            count_dir_b = 0

        else:
            writer.release()
            with lock:
                jobs[job_id].update({"status": "error", "error": f"unsupported detect_type: {detect_type}"})
            return

        writer.release()

        with lock:
            jobs[job_id]["progress"] = 97

        run_ffmpeg(tmp_avi, out_mp4)
        try:
            os.remove(tmp_avi)
        except:
            pass

        url = f"{PUBLIC_BASE_URL}/out/{job_id}.mp4"
        processed_sec = int(time.time() - t0)
        duration = fmt_duration(total / fps) if total > 0 else "—"
        processing_time_ms = int(processed_sec * 1000)

        final_a = int(countA)
        final_b = int(countB)

        # UPDATE processed_videos cuối job (đã insert ngay từ đầu)
        try:
            if processed_video_id:
                db_update_processed_video(
                    processed_video_id=processed_video_id,
                    processed_video_url=url,
                    count_a=final_a,  # Truyền biến countA đã tính được
                    count_b=final_b,  # Truyền biến countB đã tính được
                    processing_time_ms=processing_time_ms,
                    processed_by=processed_by
                )
        except Exception as db_e:
            with lock:
                jobs[job_id]["db_error"] = str(db_e)

        with lock:
            j = jobs[job_id]
            done_row = {
                "job_id": job_id,
                "filename": filename,
                "created_at": int(time.time()),
                "duration": duration,
                "processed_sec": processed_sec,
                "detect_type": detect_type,
                "url": url,
                "processed_video_id": processed_video_id,
                "violations_saved": int(j.get("violations_saved", 0)),

                # count
                "count_zone_a": int(j.get("count_zone_a", j.get("enterA", 0))),
                "count_zone_b": int(j.get("count_zone_b", j.get("enterB", 0))),

                # legacy/debug
                "inA": int(j.get("inA", 0)),
                "inB": int(j.get("inB", 0)),
                "enterA": int(j.get("enterA", 0)),
                "enterB": int(j.get("enterB", 0)),
                "count_A2B": int(j.get("count_A2B", 0)),
                "count_B2A": int(j.get("count_B2A", 0)),

                # speeding
                "max_speed": j.get("max_speed"),
                "over_speed": int(j.get("over_speed", 0)),
                "ppm": j.get("ppm"),

                # red/yellow light
                "red_light_state": j.get("red_light_state"),
                "red_light_violations": int(j.get("red_light_violations", 0)),
                "red_light_violations_red": int(j.get("red_light_violations_red", 0)),
                "yellow_light_violations": int(j.get("yellow_light_violations", 0)),
            }

            jobs[job_id].update({
                "status": "done",
                "progress": 100,
                "url": url,
            })
            history.insert(0, done_row)
            save_db()

    except Exception as e:
        with lock:
            jobs[job_id].update({"status": "error", "error": str(e)})


# =========================
# API
# =========================
@app.post("/jobs")
def create_job():
    f = request.files.get("video")
    if not f:
        return jsonify({"error": "missing video"}), 400

    zone_id = parse_int(request.form.get("zone_id"), default=None, minv=1, maxv=10**12)
    if not zone_id:
        return jsonify({"error": "missing zone_id"}), 400

    processed_by = parse_int(request.form.get("processed_by"), default=1, minv=1, maxv=10**12)

    detect_type = (request.form.get("detect_type") or "count").strip()
    if detect_type not in {"count", "speeding", "red_light"}:
        return jsonify({"error": "detect_type must be one of: count, speeding, red_light"}), 400

    zones_text = request.form.get("zones", "")
    try:
        polys, base = parse_zones_text(zones_text)
    except Exception as e:
        return jsonify({"error": f"invalid zones: {e}"}), 400

    if len(polys) < 1:
        return jsonify({"error": "need >=1 poly/line"}), 400

    if detect_type == "count":
        if len(polys[0]) < 3:
            return jsonify({"error": "count needs polygon[0] with >=3 points"}), 400
        if len(polys) >= 2 and len(polys[1]) < 3:
            return jsonify({"error": "count polygon[1] must have >=3 points (or remove it)"}), 400

    if detect_type == "speeding":
        if len(polys[0]) < 3:
            return jsonify({"error": "speeding needs polygon[0] with >=3 points (ROI)"}), 400

    if detect_type == "red_light":
        if len(polys[0]) < 2:
            return jsonify({"error": "red_light needs polygons[0]=stopLine (2 pts or thin-rect >=3)"}), 400
        if len(polys) >= 2 and len(polys[1]) < 3:
            return jsonify({"error": "red_light polygons[1]=trafficLightROI must have >=3 points"}), 400

    job_id = uuid.uuid4().hex
    in_path = os.path.join(UPLOAD, f"{job_id}.mp4")
    f.save(in_path)

    cap = cv2.VideoCapture(in_path)
    W = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH) or 1280)
    H = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT) or 720)
    cap.release()

    bw = parse_int(request.form.get("base_w"), default=None, minv=1, maxv=10000)
    bh = parse_int(request.form.get("base_h"), default=None, minv=1, maxv=10000)
    if bw and bh:
        base = (bw, bh)

    scaled = []
    for poly in polys:
        if is_normalized(poly):
            scaled.append([(x * W, y * H) for x, y in poly])
        elif base:
            bW, bH = base
            scaled.append(scale_poly(poly, bW, bH, W, H))
        else:
            scaled.append(poly)

    max_speed = parse_int(request.form.get("max_speed"), default=None, minv=0, maxv=300)
    ppm = parse_float(request.form.get("ppm"), default=None, minv=1.0, maxv=500.0)

    # INSERT processed_videos NGAY LẬP TỨC (thấy record liền)
    out_url_pred = f"{PUBLIC_BASE_URL}/out/{job_id}.mp4"
    try:
        processed_video_id = db_insert_processed_video(
            file_name=f.filename or f"{job_id}.mp4",
            processed_video_url=out_url_pred,  # placeholder (file sẽ có sau)
            zone_id=zone_id,
            count_a=0,
            count_b=0,
            processing_time_ms=0,
            processed_by=processed_by,
        )
    except Exception as e:
        return jsonify({"error": f"db insert processed_videos failed: {e}"}), 500

    with lock:
        jobs[job_id] = {
            "status": "queued",
            "progress": 0,
            "url": out_url_pred,
            "error": None,
            "db_error": None,

            "filename": f.filename or f"{job_id}.mp4",
            "in_path": in_path,
            "detect_type": detect_type,
            "polys": scaled,

            "zone_id": int(zone_id),
            "processed_by": int(processed_by),

            # đã có ngay
            "processed_video_id": int(processed_video_id),
            "violations_saved": 0,

            # count
            "count_zone_a": 0,
            "count_zone_b": 0,

            # legacy/debug
            "inA": 0,
            "inB": 0,
            "enterA": 0,
            "enterB": 0,
            "count_A2B": 0,
            "count_B2A": 0,

            # speeding
            "max_speed": max_speed,
            "over_speed": 0,
            "ppm": ppm,

            # red/yellow light
            "red_light_state": "unknown",
            "red_light_violations": 0,
            "red_light_violations_red": 0,
            "yellow_light_violations": 0,
        }

    threading.Thread(target=worker, args=(job_id,), daemon=True).start()
    return jsonify({"job_id": job_id, "processed_video_id": int(processed_video_id)}), 200


@app.get("/jobs/<job_id>")
def get_job(job_id):
    with lock:
        j = jobs.get(job_id)
        if not j:
            return jsonify({"error": "not found"}), 404
        return jsonify({
            "job_id": job_id,
            "status": j["status"],
            "progress": j["progress"],
            "url": j.get("url"),
            "error": j.get("error"),
            "db_error": j.get("db_error"),
            "filename": j["filename"],
            "detect_type": j.get("detect_type", "count"),

            "processed_video_id": j.get("processed_video_id"),
            "violations_saved": j.get("violations_saved", 0),

            # count
            "count_zone_a": j.get("count_zone_a", j.get("enterA", 0)),
            "count_zone_b": j.get("count_zone_b", j.get("enterB", 0)),

            # legacy/debug
            "inA": j.get("inA", 0),
            "inB": j.get("inB", 0),
            "enterA": j.get("enterA", 0),
            "enterB": j.get("enterB", 0),
            "count_A2B": j.get("count_A2B", 0),
            "count_B2A": j.get("count_B2A", 0),

            # speeding
            "max_speed": j.get("max_speed"),
            "over_speed": j.get("over_speed", 0),
            "ppm": j.get("ppm"),

            # red/yellow light
            "red_light_state": j.get("red_light_state", "unknown"),
            "red_light_violations": j.get("red_light_violations", 0),
            "red_light_violations_red": j.get("red_light_violations_red", 0),
            "yellow_light_violations": j.get("yellow_light_violations", 0),
        })


@app.get("/jobs")
def list_jobs():
    with lock:
        return jsonify(history)


@app.get("/jobs/<job_id>/violations")
def list_job_violations(job_id):
    pv_id = None
    with lock:
        j = jobs.get(job_id)
        if j:
            pv_id = j.get("processed_video_id")

    if not pv_id:
        with lock:
            for r in history:
                if str(r.get("job_id")) == str(job_id):
                    pv_id = r.get("processed_video_id")
                    break

    if not pv_id:
        try:
            pv_id = db_find_processed_video_id_by_job(job_id)
        except Exception:
            pv_id = None

    if not pv_id:
        return jsonify([])

    try:
        rows = db_list_violations(int(pv_id))
        return jsonify(rows)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.get("/out/<path:fn>")
def out_file(fn):
    return send_from_directory(OUT, fn, as_attachment=False, mimetype="video/mp4")


@app.get("/evidence/<job_id>/<path:fn>")
def evidence_file(job_id, fn):
    return send_from_directory(os.path.join(EVID, job_id), fn, as_attachment=False, mimetype="image/jpeg")


# init
load_db()

if __name__ == "__main__":
    app.run(host=HOST, port=PORT, threaded=True)