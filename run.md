## Akses dari HP (WiFi yang sama)

Klik dua kali `start-lan.bat`, lalu buka alamat `https://<IP-laptop>:8443` yang tampil di layar dari HP.

## Manual (hanya di laptop)

terminal 1

cd ai-service
.\.venv\Scripts\python.exe -m uvicorn main:app --host 127.0.0.1 --port 8000

terminal 2

cd web
php artisan serve --port=8080
