"""Proxy HTTPS sederhana agar web bisa dibuka dari HP di jaringan WiFi yang sama.

Browser HP hanya mengizinkan akses kamera lewat HTTPS, sedangkan `php artisan serve`
hanya melayani HTTP. Proxy ini menerima koneksi TLS lalu meneruskan byte mentah ke server Laravel.

Koneksi HTTP biasa (tanpa TLS) di port yang sama dilayani dengan halaman bantuan untuk
mengunduh sertifikat CA lokal, supaya HP bisa memercayai sertifikat HTTPS-nya.

Pemakaian: python https_proxy.py <cert.pem> <key.pem> <ca.pem> <port_https> <port_laravel>
"""

import socket
import ssl
import sys
import threading

TLS_HANDSHAKE_BYTE = 0x16


def pipe(source: socket.socket, target: socket.socket) -> None:
    try:
        while data := source.recv(65536):
            target.sendall(data)
    except OSError:
        pass
    finally:
        for sock in (source, target):
            try:
                sock.shutdown(socket.SHUT_RDWR)
            except OSError:
                pass


def help_page(host: str, listen_port: int) -> bytes:
    host_name = host.split(':')[0] or 'IP-laptop'
    https_url = f'https://{host_name}:{listen_port}/'
    return f'''<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Presensi Aman - Sertifikat</title>
<style>
body{{margin:0;font-family:system-ui,sans-serif;background:#f1f5fb;color:#0f172a;line-height:1.55}}
main{{max-width:560px;margin:0 auto;padding:24px 16px}}
.card{{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:20px;margin-bottom:16px}}
a.btn{{display:block;text-align:center;padding:14px;border-radius:12px;font-weight:700;text-decoration:none;margin:12px 0}}
.primary{{background:#4f46e5;color:#fff}} .secondary{{border:1px solid #c7d2fe;color:#4338ca}}
li{{margin:6px 0}} h1{{font-size:1.5rem}} h2{{font-size:1.1rem;margin-top:0}}
</style></head><body><main>
<h1>Presensi Aman</h1>
<div class="card">
<h2>1. Pasang sertifikat (sekali saja)</h2>
<a class="btn secondary" href="/ca.crt">Unduh sertifikat CA</a>
<p><b>Android:</b></p>
<ol><li>Unduh file di atas.</li>
<li>Buka <i>Setelan</i> &rarr; cari <i>"sertifikat CA"</i> (biasanya di Keamanan &rarr; Enkripsi &amp; kredensial &rarr; Instal sertifikat &rarr; <i>Sertifikat CA</i>).</li>
<li>Pilih <i>Tetap instal</i>, lalu pilih file <code>presensi-aman-ca.crt</code>.</li></ol>
<p><b>iPhone:</b></p>
<ol><li>Buka halaman ini di <b>Safari</b>, unduh file di atas, lalu izinkan profil.</li>
<li><i>Pengaturan</i> &rarr; <i>Profil Diunduh</i> &rarr; <i>Instal</i>.</li>
<li><i>Pengaturan</i> &rarr; Umum &rarr; Mengenai &rarr; <i>Pengaturan Kepercayaan Sertifikat</i> &rarr; aktifkan <i>Presensi Aman Local CA</i>.</li></ol>
</div>
<div class="card">
<h2>2. Buka aplikasi</h2>
<a class="btn primary" href="{https_url}">Buka {https_url}</a>
<p>Tutup lalu buka ulang Chrome setelah memasang sertifikat.</p>
</div>
</main></body></html>'''.encode('utf-8')


def serve_plain_http(client: socket.socket, ca_file: str, listen_port: int) -> None:
    try:
        client.settimeout(10)
        request = b''
        while b'\r\n\r\n' not in request and len(request) < 16384:
            chunk = client.recv(4096)
            if not chunk:
                break
            request += chunk

        lines = request.decode('latin-1').split('\r\n')
        path = lines[0].split(' ')[1] if lines and len(lines[0].split(' ')) > 1 else '/'
        host = next((line.split(':', 1)[1].strip() for line in lines[1:] if line.lower().startswith('host:')), '')

        if path.startswith('/ca.crt'):
            with open(ca_file, 'rb') as handle:
                body = handle.read()
            headers = ('Content-Type: application/x-x509-ca-cert\r\n'
                       'Content-Disposition: attachment; filename="presensi-aman-ca.crt"\r\n')
        else:
            body = help_page(host, listen_port)
            headers = 'Content-Type: text/html; charset=utf-8\r\n'

        client.sendall((f'HTTP/1.1 200 OK\r\n{headers}Content-Length: {len(body)}\r\n'
                        'Cache-Control: no-store\r\nConnection: close\r\n\r\n').encode('latin-1') + body)
    except OSError:
        pass
    finally:
        client.close()


def handle(client: socket.socket, context: ssl.SSLContext, ca_file: str, listen_port: int, target_port: int) -> None:
    try:
        client.settimeout(15)
        first_byte = client.recv(1, socket.MSG_PEEK)
        if not first_byte:
            client.close()
            return

        if first_byte[0] != TLS_HANDSHAKE_BYTE:
            serve_plain_http(client, ca_file, listen_port)
            return

        tls_client = context.wrap_socket(client, server_side=True)
        tls_client.settimeout(None)
    except (OSError, ssl.SSLError):
        # Biasanya HP menolak sertifikat sebelum pengguna menekan "Lanjutkan".
        client.close()
        return

    try:
        upstream = socket.create_connection(('127.0.0.1', target_port))
    except OSError:
        tls_client.close()
        return

    threading.Thread(target=pipe, args=(tls_client, upstream), daemon=True).start()
    pipe(upstream, tls_client)
    tls_client.close()
    upstream.close()


def main(cert: str, key: str, ca_file: str, listen_port: int, target_port: int) -> None:
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    context.load_cert_chain(cert, key)

    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.bind(('0.0.0.0', listen_port))
    server.listen(128)
    server.settimeout(1)
    print(f'Proxy HTTPS aktif di port {listen_port} -> http://127.0.0.1:{target_port}', flush=True)

    while True:
        try:
            client, _address = server.accept()
        except socket.timeout:
            continue  # memberi kesempatan Ctrl+C diproses di Windows
        threading.Thread(target=handle, args=(client, context, ca_file, listen_port, target_port), daemon=True).start()


if __name__ == '__main__':
    if len(sys.argv) != 6:
        sys.exit(__doc__)

    try:
        main(sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4]), int(sys.argv[5]))
    except KeyboardInterrupt:
        pass
