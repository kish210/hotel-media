#!/usr/bin/env python3
"""
SignageCMS In-Flight Bridge v1.0
=================================
Reads GPS (via gpsd over TCP) and ADS-B (via dump1090 JSON API)
Exposes a REST API on port 5055 so SignageCMS can pull live data.
Optionally pushes telemetry directly to SignageCMS API.

Requirements:
    sudo apt-get install gpsd python3   # standard library only, no pip needed

Usage:
    python3 inflight_bridge.py
    # or as service: systemctl start inflight-bridge

Config file: /etc/inflight-bridge/config.json
"""

import json
import os
import socket
import threading
import time
import math
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.request import urlopen, Request
from urllib.error import URLError
from datetime import datetime, timezone

# ── Shared state (thread-safe reads for simple types) ─────────────────────────
GPS = {
    "fix":       False,
    "mode":      0,       # 0=no data, 1=no fix, 2=2D, 3=3D
    "lat":       None,
    "lng":       None,
    "alt_m":     None,
    "alt_ft":    None,
    "speed_kmh": None,
    "heading":   None,
    "satellites_used": 0,
    "hdop":      None,
    "timestamp": None,
    "error":     None,
}

ADSB = {
    "aircraft":   [],
    "total":      0,
    "updated_at": None,
    "error":      None,
}

PUSH_STATUS = {
    "last_push_at":  None,
    "push_count":    0,
    "last_error":    None,
    "push_enabled":  False,
}

CONFIG_FILE = os.environ.get("BRIDGE_CONFIG", "/etc/inflight-bridge/config.json")
CONFIG_LOCK  = threading.Lock()
CONFIG = {}

# ── Config ─────────────────────────────────────────────────────────────────────
DEFAULTS = {
    "port":             5055,
    "gpsd_host":        "localhost",
    "gpsd_port":        2947,
    "dump1090_url":     "http://localhost:8080",
    "dump1090_enabled": True,
    "dump1090_interval": 5,
    "signagecms_url":   "",
    "flight_id":        None,
    "api_token":        "",
    "push_enabled":     False,
    "push_interval":    10,
}

def load_config():
    global CONFIG
    cfg = dict(DEFAULTS)
    try:
        with open(CONFIG_FILE, "r") as f:
            cfg.update(json.load(f))
    except FileNotFoundError:
        pass
    except Exception as e:
        print(f"[Config] Error loading {CONFIG_FILE}: {e}")
    with CONFIG_LOCK:
        CONFIG = cfg
    return cfg

def save_config():
    os.makedirs(os.path.dirname(CONFIG_FILE), exist_ok=True)
    with CONFIG_LOCK:
        data = dict(CONFIG)
    with open(CONFIG_FILE, "w") as f:
        json.dump(data, f, indent=2)

def get_config(key, default=None):
    with CONFIG_LOCK:
        return CONFIG.get(key, default)

def set_config(updates: dict):
    with CONFIG_LOCK:
        CONFIG.update(updates)

# ── GPSD Reader thread ─────────────────────────────────────────────────────────
class GPSDReader(threading.Thread):
    """Connects to gpsd over TCP and parses JSON TPV/SKY messages."""

    def __init__(self):
        super().__init__(daemon=True, name="gpsd-reader")

    def run(self):
        while True:
            host = get_config("gpsd_host", "localhost")
            port = int(get_config("gpsd_port", 2947))
            try:
                self._connect_and_read(host, port)
            except Exception as e:
                GPS["fix"]   = False
                GPS["error"] = str(e)
                print(f"[GPS] Connection error: {e} — retrying in 5s")
                time.sleep(5)

    def _connect_and_read(self, host, port):
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(15)
        sock.connect((host, port))
        print(f"[GPS] Connected to gpsd at {host}:{port}")
        GPS["error"] = None

        # Enable JSON watch mode
        sock.sendall(b'?WATCH={"enable":true,"json":true,"scaled":true}\n')
        sock.settimeout(5)

        buf = b""
        while True:
            try:
                chunk = sock.recv(4096)
                if not chunk:
                    raise ConnectionResetError("gpsd closed connection")
                buf += chunk
                while b"\n" in buf:
                    line, buf = buf.split(b"\n", 1)
                    self._process_line(line.strip())
            except socket.timeout:
                continue  # Normal — gpsd sends updates as they arrive

    def _process_line(self, line: bytes):
        if not line:
            return
        try:
            obj = json.loads(line)
        except json.JSONDecodeError:
            return

        cls = obj.get("class")

        if cls == "TPV":
            mode = obj.get("mode", 0)
            GPS["mode"] = mode
            GPS["fix"]  = mode >= 2

            if "lat"   in obj: GPS["lat"]     = round(float(obj["lat"]),   6)
            if "lon"   in obj: GPS["lng"]     = round(float(obj["lon"]),   6)
            if "alt"   in obj:
                alt_m = float(obj["alt"])
                GPS["alt_m"]  = round(alt_m, 1)
                GPS["alt_ft"] = int(alt_m * 3.28084)
            if "speed" in obj:
                # gpsd returns m/s; convert to km/h
                GPS["speed_kmh"] = round(float(obj["speed"]) * 3.6, 1)
            if "track" in obj:
                GPS["heading"] = round(float(obj["track"]), 1)
            GPS["timestamp"] = obj.get("time")

        elif cls == "SKY":
            sats  = obj.get("satellites", [])
            used  = [s for s in sats if s.get("used", False)]
            GPS["satellites_used"] = len(used)
            if "hdop" in obj:
                GPS["hdop"] = round(float(obj["hdop"]), 2)


# ── ADS-B Reader thread (dump1090 JSON API) ────────────────────────────────────
class ADSBReader(threading.Thread):
    """Polls dump1090's aircraft.json endpoint every N seconds."""

    def __init__(self):
        super().__init__(daemon=True, name="adsb-reader")

    def run(self):
        while True:
            interval = int(get_config("dump1090_interval", 5))
            if get_config("dump1090_enabled", True):
                base = get_config("dump1090_url", "http://localhost:8080").rstrip("/")
                # dump1090-fa uses /data/aircraft.json; mutability uses /data.json
                for path in ["/data/aircraft.json", "/dump1090/data/aircraft.json", "/data.json"]:
                    try:
                        with urlopen(base + path, timeout=4) as r:
                            data = json.loads(r.read())
                            ac   = data.get("aircraft", data.get("acList", []))
                            ADSB["aircraft"]   = ac[:100]   # cap for memory
                            ADSB["total"]      = len(ac)
                            ADSB["updated_at"] = datetime.now(timezone.utc).isoformat()
                            ADSB["error"]      = None
                            break
                    except URLError:
                        continue
                    except Exception as e:
                        ADSB["error"] = str(e)
                        ADSB["aircraft"] = []
            time.sleep(interval)


# ── Automatic Push thread ──────────────────────────────────────────────────────
class SignagePusher(threading.Thread):
    """Periodically pushes GPS telemetry to SignageCMS /api/v1/inflight/{id}/live"""

    def __init__(self):
        super().__init__(daemon=True, name="signage-pusher")

    def run(self):
        while True:
            interval = max(5, int(get_config("push_interval", 10)))
            time.sleep(interval)

            if not get_config("push_enabled", False):
                PUSH_STATUS["push_enabled"] = False
                continue

            PUSH_STATUS["push_enabled"] = True
            cms_url   = get_config("signagecms_url", "").rstrip("/")
            flight_id = get_config("flight_id")
            token     = get_config("api_token", "")

            if not (cms_url and flight_id and token):
                continue
            if not GPS["fix"]:
                continue

            try:
                phase = self._detect_phase()
                payload = json.dumps({
                    "altitude_ft":  GPS["alt_ft"]    or 0,
                    "speed_kmh":    int(GPS["speed_kmh"] or 0),
                    "heading_deg":  int(GPS["heading"]   or 0),
                    "phase":        phase,
                }).encode()

                req = Request(
                    f"{cms_url}/api/v1/inflight/{flight_id}/live",
                    data=payload,
                    method="PUT",
                    headers={
                        "Content-Type":  "application/json",
                        "Authorization": f"Bearer {token}",
                    },
                )
                with urlopen(req, timeout=8) as r:
                    resp = json.loads(r.read())
                    if resp.get("success"):
                        PUSH_STATUS["last_push_at"] = datetime.now(timezone.utc).isoformat()
                        PUSH_STATUS["push_count"]  += 1
                        PUSH_STATUS["last_error"]   = None
                    else:
                        PUSH_STATUS["last_error"] = resp.get("message", "unknown error")
            except Exception as e:
                PUSH_STATUS["last_error"] = str(e)

    @staticmethod
    def _detect_phase() -> str:
        alt = GPS["alt_ft"]    or 0
        spd = GPS["speed_kmh"] or 0
        if alt < 50  and spd < 20:  return "preflight"
        if alt < 200 and spd < 120: return "taxi"
        if alt < 2000:               return "takeoff"
        if alt < 8000:               return "climb"
        if alt >= 25000 and spd > 600: return "cruise"
        if alt >= 8000:              return "descent"
        return "approach"


# ── HTTP API ───────────────────────────────────────────────────────────────────
class APIHandler(BaseHTTPRequestHandler):

    def do_OPTIONS(self):
        self.send_response(200)
        self._cors()
        self.end_headers()

    def do_GET(self):
        path = self.path.split("?")[0].rstrip("/")

        if path == "/api/status":
            self._json({
                "version":    "1.0",
                "uptime_s":   int(time.time() - START_TIME),
                "gps":        GPS,
                "adsb":       {"total": ADSB["total"], "updated_at": ADSB["updated_at"], "error": ADSB["error"]},
                "push":       PUSH_STATUS,
                "config": {
                    "push_enabled":   get_config("push_enabled"),
                    "push_interval":  get_config("push_interval"),
                    "flight_id":      get_config("flight_id"),
                    "signagecms_url": get_config("signagecms_url"),
                    "dump1090_enabled": get_config("dump1090_enabled"),
                    "has_token":      bool(get_config("api_token")),
                },
            })

        elif path == "/api/gps":
            self._json(GPS)

        elif path == "/api/adsb":
            self._json({
                "aircraft":   ADSB["aircraft"][:50],
                "total":      ADSB["total"],
                "updated_at": ADSB["updated_at"],
            })

        elif path == "/api/config":
            safe = {k: v for k, v in CONFIG.items() if k != "api_token"}
            safe["has_token"] = bool(CONFIG.get("api_token"))
            self._json(safe)

        elif path == "/api/health":
            self._json({
                "ok":      True,
                "gps_fix": GPS["fix"],
                "adsb_ok": ADSB["error"] is None,
                "ts":      datetime.now(timezone.utc).isoformat(),
            })

        else:
            self.send_response(404)
            self._cors()
            self.end_headers()

    def do_POST(self):
        path = self.path.split("?")[0].rstrip("/")
        length = int(self.headers.get("Content-Length", 0))
        body   = {}
        if length:
            try:
                body = json.loads(self.rfile.read(length))
            except Exception:
                pass

        if path == "/api/config":
            allowed = [
                "signagecms_url", "flight_id", "api_token",
                "push_enabled", "push_interval",
                "dump1090_url", "dump1090_enabled", "dump1090_interval",
                "gpsd_host", "gpsd_port",
            ]
            updates = {k: body[k] for k in allowed if k in body}
            # Type coercion
            for boolKey in ("push_enabled", "dump1090_enabled"):
                if boolKey in updates:
                    updates[boolKey] = bool(updates[boolKey])
            for intKey in ("push_interval", "dump1090_interval", "gpsd_port"):
                if intKey in updates:
                    updates[intKey] = int(updates[intKey])
            set_config(updates)
            save_config()
            self._json({"ok": True, "saved": list(updates.keys())})

        else:
            self.send_response(404)
            self._cors()
            self.end_headers()

    def _json(self, data):
        body = json.dumps(data, default=str).encode("utf-8")
        self.send_response(200)
        self._cors()
        self.send_header("Content-Type",   "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _cors(self):
        self.send_header("Access-Control-Allow-Origin",  "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization")

    def log_message(self, fmt, *args):
        pass   # silence default request log (noisy on embedded device)


# ── Entry point ────────────────────────────────────────────────────────────────
START_TIME = time.time()

if __name__ == "__main__":
    load_config()
    port = int(get_config("port", 5055))

    print("╔══════════════════════════════════════════════════╗")
    print("║   SignageCMS In-Flight Bridge v1.0               ║")
    print("╚══════════════════════════════════════════════════╝")
    print(f"  GPS  → {get_config('gpsd_host')}:{get_config('gpsd_port')}")
    print(f"  ADSB → {get_config('dump1090_url')}")
    print(f"  API  → http://0.0.0.0:{port}/api/status")
    if get_config("push_enabled"):
        print(f"  Push → {get_config('signagecms_url')} (flight {get_config('flight_id')})")
    print()

    GPSDReader().start()
    ADSBReader().start()
    SignagePusher().start()

    server = HTTPServer(("0.0.0.0", port), APIHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\n[Bridge] Stopped.")
