# 🌐 MikroTik Sentinel NMS (Network Monitoring System)

Sistem Monitoring Jaringan & Pelacak Aktivitas Aplikasi (YouTube, WhatsApp, TikTok, Instagram, Netflix, dll) berbasis **Laravel 12 + PostgreSQL** yang didesain khusus untuk router **MikroTik RouterOS (v6 & v7)** dengan pendekatan **Zero-CPU Overhead (100% Production-Safe)**.

---

## ✨ Fitur Utama

1. **⚡ Zero CPU Overhead (Aman untuk Router Produksi)**:
   - **TIDAK menggunakan** Layer 7 (L7) Regex, Mangle Connection Marking, atau Deep Packet Inspection (DPI) pada MikroTik.
   - MikroTik hanya bertugas melempar log query DNS melalui protokol Syslog UDP 514 standar. Seluruh beban parsing, regex domain, dan pengelompokan aplikasi dikerjakan oleh Laravel Daemon.
2. **📱 Real-Time Live App Activity Stream**:
   - Deteksi real-time aplikasi populer: YouTube, TikTok, WhatsApp, Instagram, Facebook, Netflix, Spotify, Shopee, Tokopedia, Mobile Legends, Roblox, Zoom, dll.
   - Fitur **Debounce & Grouping**: Mengelompokkan ratusan burst request identik dalam jendela 20 detik menjadi 1 baris log dengan counter (`xN hits`).
   - Kontrol kecepatan feed: *Slow (7s)*, *Relaxed (5s)*, atau *Normal (3s)*.
   - Opsi filter *Hide Background/OS Traffic*.
3. **🖥️ Connected Devices Tracker**:
   - Sinkronisasi otomatis data perangkat dari `/ip/dhcp-server/lease` dan `/ip/arp`.
   - Menampilkan Hostname, IP Address, MAC Address, Interface, Status Online/Offline, dan akumulasi aktivitas harian.
4. **🏢 Multi-Router Management**:
   - Mendukung banyak perangkat MikroTik sekaligus (Kantor Utama, Cabang, Router Lt 2, dll).
   - Sinkronisasi perangkat per router atau sekaligus (*Sync All*).
   - Filter stream aktivitas dan daftar perangkat per router.
   - Password API router tersimpan aman dengan enkripsi **AES-256**.
5. **🔌 Fleksibilitas Protokol API**:
   - Mendukung **Socket API (Port 8728 binary protocol)** untuk RouterOS v6 & v7.
   - Mendukung **REST API (Port 80/443)** untuk RouterOS v7.1+.
6. **🎯 Custom App Signature Manager**:
   - Tambah atau edit signature aplikasi dan pola domain langsung dari Web UI tanpa perlu restart server.
   - Algoritma pencocokan domain yang ketat (subdomain matching) untuk menghindari false-positive.

---

## 🏗️ Arsitektur Sistem

```
┌─────────────────────────────────┐
│     MikroTik Router (RouterOS)  │
│  - No Mangle / No L7 Regex      │
│  - Remote DNS Log over UDP 514  │
└────────────────┬────────────────┘
                 │
                 ▼ UDP 514 (Syslog)
┌──────────────────────────────────────────────┐
│          Laravel Syslog Daemon               │
│  (php artisan syslog:listen --port=514)      │
│  - Multi-line DNS log parser                 │
│  - 20s Window Debouncer & Hit Counter        │
│  - Signature Matching (Exact & Subdomain)    │
└────────────────┬─────────────────────────────┘
                 │
                 ▼ PostgreSQL Database
┌──────────────────────────────────────────────┐
│           Web UI & REST Dashboard            │
│  - Live Activity Stream                      │
│  - Device Inventory & Inspector              │
│  - Multi-Router Control Panel                │
└──────────────────────────────────────────────┘
```

---

## 🚀 Panduan Instalasi & Menjalankan

### 1. Kebutuhan Sistem
- PHP >= 8.2 (dengan ekstensi `sockets`, `pdo_pgsql`, `openssl`)
- Composer
- PostgreSQL >= 14
- Router MikroTik RouterOS v6 atau v7

### 2. Setup Proyek
```bash
# Clone repository
git clone <URL_REPOSITORY_ANDA>
cd Monitoring

# Install dependensi PHP
composer install

# Siapkan file environment
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Konfigurasi Database PostgreSQL
Sesuaikan kredensial database di `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nms_monitoring
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Jalankan migrasi database:
```bash
php artisan migrate
```

---

## ⚙️ Konfigurasi di MikroTik RouterOS

Buka Terminal di Winbox atau SSH ke MikroTik Anda, lalu jalankan 2 perintah berikut:

### Langkah 1: Arahkan Remote DNS Logging ke IP Server Laravel
```routeros
# Ganti 192.168.10.238 dengan IP server tempat Laravel NMS ini berjalan
/system logging action add name=nmsremote target=remote remote=192.168.10.238 remote-port=514
/system logging add topics=dns,packet action=nmsremote
```

### Langkah 2: Buat User API MikroTik (Read-Only)
```routeros
/user group add name=nms-group policy=read,api,test,!local,!telnet,!ssh,!ftp,!reboot,!write,!policy,!password,!web,!sniff,!sensitive,!romon
/user add name=nmsuser group=nms-group password="PasswordKuatAnda!" comment="NMS Monitoring Agent"
/ip service enable api
```

---

## 🏃 Menjalankan Aplikasi

Jalankan 2 service berikut:

### 1. Jalankan Syslog Daemon Listener
Buka terminal dan jalankan:
```bash
php artisan syslog:listen --port=514
```

### 2. Jalankan Laravel Web Server
Di terminal terpisah, jalankan:
```bash
php artisan serve
```
Akses dashboard di browser melalui: **`http://localhost:8000`**

---

## 🧪 Pengujian (Testing)

Proyek ini dilengkapi dengan Feature & Unit Tests:
```bash
php artisan test --compact
```

---

## 🔒 Lisensi
Open-source di bawah lisensi [MIT](LICENSE).
