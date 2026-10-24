document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-enrollment]');

    if (!root) {
        return;
    }

    const poses = [
        { key: 'front', label: 'Hadap lurus ke kamera' },
        { key: 'left', label: 'Arahkan wajah ke kiri' },
        { key: 'right', label: 'Arahkan wajah ke kanan' },
        { key: 'up', label: 'Arahkan wajah ke atas' },
        { key: 'down', label: 'Arahkan wajah ke bawah' },
    ];
    const video = root.querySelector('[data-camera]');
    const canvas = root.querySelector('[data-canvas]');
    const captureButton = root.querySelector('[data-capture]');
    const label = root.querySelector('[data-pose-label]');
    const number = root.querySelector('[data-pose-number]');
    const message = root.querySelector('[data-enrollment-message]');
    const cameraStatus = root.querySelector('[data-camera-status]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let currentPose = root.dataset.enrollmentComplete === 'true' ? poses.length : 0;

    const completeEnrollment = (messageText) => {
        number.textContent = poses.length;
        label.textContent = 'Pendaftaran lengkap';
        message.textContent = messageText;
        captureButton.textContent = 'Selesai';
        captureButton.disabled = false;
        root.querySelectorAll('[data-pose]').forEach((item) => item.classList.add('complete'));
    };

    const renderPose = () => {
        const pose = poses[currentPose];
        label.textContent = pose.label;
        number.textContent = currentPose + 1;
        root.querySelectorAll('[data-pose]').forEach((item, index) => {
            item.classList.toggle('complete', index < currentPose);
            item.classList.toggle('current', index === currentPose);
        });
    };

    const cameraFrame = () => new Promise((resolve, reject) => {
        if (!video.videoWidth || !video.videoHeight) {
            reject(new Error('Kamera belum menghasilkan gambar.'));
            return;
        }

        const scale = Math.min(1, 640 / video.videoWidth);
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('Frame kamera gagal dibuat.')), 'image/jpeg', 0.9);
    });

    captureButton.addEventListener('click', async () => {
        if (currentPose === poses.length) {
            window.location.assign(root.dataset.completeUrl);
            return;
        }

        captureButton.disabled = true;
        message.textContent = 'Memeriksa kualitas pose…';

        try {
            const frame = await cameraFrame();
            const formData = new FormData();
            formData.append('pose', poses[currentPose].key);
            formData.append('frame', frame, `${poses[currentPose].key}.jpg`);
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: formData,
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Pose belum dapat disimpan.');
            }

            currentPose += 1;

            if (currentPose === poses.length) {
                completeEnrollment(data.message);
                return;
            }

            renderPose();
            message.textContent = data.message;
            captureButton.disabled = false;
        } catch (error) {
            message.textContent = error.message;
            captureButton.disabled = false;
        }
    });

    if (currentPose === poses.length) {
        cameraStatus.textContent = 'Pendaftaran wajah sudah tersimpan.';
        completeEnrollment('Pendaftaran wajah sudah lengkap. Tekan Selesai untuk kembali ke daftar mahasiswa.');
        return;
    }

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
        .then(async (stream) => {
            video.srcObject = stream;
            await video.play();
            cameraStatus.textContent = 'Kamera siap. Pastikan wajah terlihat jelas.';
            captureButton.disabled = false;
        })
        .catch((error) => {
            cameraStatus.textContent = 'Kamera tidak tersedia. Izinkan akses kamera lalu muat ulang halaman.';
            message.textContent = error.message;
        });

    renderPose();
});
