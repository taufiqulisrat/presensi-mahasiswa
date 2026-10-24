document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-attendance]');

    if (!root) {
        return;
    }

    const video = root.querySelector('[data-camera]');
    const canvas = root.querySelector('[data-canvas]');
    const startButton = root.querySelector('[data-start]');
    const instruction = root.querySelector('[data-instruction]');
    const message = root.querySelector('[data-message]');
    const cameraStatus = root.querySelector('[data-camera-status]');
    const challengeStep = root.querySelector('[data-challenge-step]');
    const challengeTimer = root.querySelector('[data-challenge-timer]');
    const voiceToggle = root.querySelector('[data-voice-toggle]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let attemptId = null;
    let videoIntervalId = null;
    let snapshotTimerId = null;
    let snapshotCountdownId = null;
    let snapshotDeadline = null;
    let challengeNumber = null;
    let challengeMode = null;
    let isProcessingFrame = false;
    let lastSpokenInstruction = null;
    const speechSupported = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    const voiceStorageKey = 'presensi-voice-enabled';
    let speechEnabled = speechSupported;
    let preferredVoice = null;
    let currentUtterance = null;
    let speakTimerId = null;

    try {
        speechEnabled = speechSupported && window.localStorage.getItem(voiceStorageKey) !== 'false';
    } catch (error) {
        // Penyimpanan lokal tidak tersedia; gunakan pengaturan bawaan.
    }

    const pickIndonesianVoice = () => {
        const voices = window.speechSynthesis.getVoices()
            .filter((voice) => voice.lang.toLowerCase().replace('_', '-').startsWith('id'));

        preferredVoice = voices.find((voice) => /google.*(female|wanita)/i.test(voice.name))
            || voices.find((voice) => /google/i.test(voice.name))
            || voices.find((voice) => voice.localService)
            || voices[0]
            || null;
    };

    const speakInstruction = (text, force = false) => {
        if (!speechEnabled || !text || (!force && text === lastSpokenInstruction)) {
            return;
        }

        lastSpokenInstruction = text;
        window.clearTimeout(speakTimerId);

        const synth = window.speechSynthesis;
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        utterance.rate = 1.1;
        utterance.pitch = 1;

        if (!preferredVoice) {
            pickIndonesianVoice();
        }

        if (preferredVoice) {
            utterance.voice = preferredVoice;
        }

        // Simpan referensi agar utterance tidak dibersihkan garbage collector sebelum selesai (bug Chrome).
        currentUtterance = utterance;
        utterance.onend = () => {
            if (currentUtterance === utterance) {
                currentUtterance = null;
            }
        };

        const speakNow = () => {
            synth.resume();
            synth.speak(utterance);
        };

        if (synth.speaking || synth.pending) {
            // Chrome kadang mengabaikan speak() yang dipanggil tepat setelah cancel().
            synth.cancel();
            speakTimerId = window.setTimeout(speakNow, 60);
            return;
        }

        speakNow();
    };

    const renderVoiceToggle = () => {
        voiceToggle.textContent = `Suara: ${speechEnabled ? 'Aktif' : 'Nonaktif'}`;
        voiceToggle.setAttribute('aria-pressed', String(speechEnabled));
    };

    if (!speechSupported) {
        voiceToggle.hidden = true;
    } else {
        pickIndonesianVoice();
        window.speechSynthesis.addEventListener?.('voiceschanged', pickIndonesianVoice);
        renderVoiceToggle();
        voiceToggle.addEventListener('click', () => {
            speechEnabled = !speechEnabled;
            renderVoiceToggle();

            try {
                window.localStorage.setItem(voiceStorageKey, String(speechEnabled));
            } catch (error) {
                // Abaikan jika penyimpanan lokal diblokir.
            }

            if (speechEnabled) {
                speakInstruction(instruction.textContent, true);
            } else {
                window.clearTimeout(speakTimerId);
                window.speechSynthesis.cancel();
            }
        });
    }

    const setMessage = (text, isError = false) => {
        message.textContent = text;
        message.classList.toggle('message-error', isError);
    };

    const clearSnapshotTimer = () => {
        if (snapshotTimerId) {
            window.clearTimeout(snapshotTimerId);
            snapshotTimerId = null;
        }

        if (snapshotCountdownId) {
            window.clearInterval(snapshotCountdownId);
            snapshotCountdownId = null;
        }

        snapshotDeadline = null;
    };

    const updateSnapshotCountdown = () => {
        if (snapshotDeadline === null) {
            return;
        }

        const secondsRemaining = Math.max(0, Math.ceil((snapshotDeadline - Date.now()) / 1000));
        challengeTimer.textContent = `Foto otomatis dalam ${secondsRemaining} detik`;
    };

    const stopProcessing = () => {
        if (videoIntervalId) {
            window.clearInterval(videoIntervalId);
            videoIntervalId = null;
        }

        clearSnapshotTimer();
        isProcessingFrame = false;
    };

    const cameraFrame = () => new Promise((resolve, reject) => {
        if (!video.videoWidth || !video.videoHeight) {
            reject(new Error('Kamera belum menghasilkan gambar.'));
            return;
        }

        const maximumWidth = 640;
        const scale = Math.min(1, maximumWidth / video.videoWidth);
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('Frame kamera tidak dapat dibuat.')), 'image/jpeg', 0.9);
    });

    const post = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            body,
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Permintaan tidak dapat diproses.');
        }

        return data;
    };

    const complete = async () => {
        const frame = await cameraFrame();
        const formData = new FormData();
        formData.append('attempt_id', attemptId);
        formData.append('frame', frame, 'attendance.jpg');
        const data = await post(root.dataset.completeUrl, formData);

        challengeStep.textContent = 'Presensi berhasil';
        instruction.textContent = `Selamat datang, ${data.student_name}.`;
        challengeTimer.textContent = 'Verifikasi selesai';
        speakInstruction(`Verifikasi berhasil. Selamat datang, ${data.student_name}.`);
        setMessage(data.message);
        startButton.dataset.completed = 'true';
        startButton.textContent = 'Selesai';
        startButton.disabled = false;
    };

    const scheduleSnapshot = () => {
        clearSnapshotTimer();
        snapshotDeadline = Date.now() + 3000;
        updateSnapshotCountdown();
        snapshotCountdownId = window.setInterval(updateSnapshotCountdown, 100);
        snapshotTimerId = window.setTimeout(() => {
            clearSnapshotTimer();
            processFrame();
        }, 3000);
    };

    const activateChallenge = (data, forceInstruction = false) => {
        const isNewChallenge = data.challenge_number !== challengeNumber || data.challenge_mode !== challengeMode;
        challengeNumber = data.challenge_number;
        challengeMode = data.challenge_mode;
        challengeStep.textContent = `Challenge ${data.challenge_number} dari ${data.challenge_total}`;
        instruction.textContent = data.instruction;

        if (forceInstruction || isNewChallenge) {
            speakInstruction(data.instruction, forceInstruction);
        }

        if (challengeMode === 'snapshot') {
            if (videoIntervalId) {
                window.clearInterval(videoIntervalId);
                videoIntervalId = null;
            }

            setMessage(data.message || 'Posisikan wajah sesuai arahan. Foto tetap diambil saat timer selesai.');
            scheduleSnapshot();
            return;
        }

        clearSnapshotTimer();
        challengeTimer.textContent = 'Mode video: kedipkan mata perlahan sampai terdeteksi';
        setMessage(data.message || 'Kedipkan mata perlahan. Video hanya digunakan untuk verifikasi kedip.');

        if (!videoIntervalId) {
            videoIntervalId = window.setInterval(processFrame, 350);
        }
    };

    const processFrame = async () => {
        if (isProcessingFrame || attemptId === null) {
            return;
        }

        isProcessingFrame = true;

        try {
            const frame = await cameraFrame();
            const formData = new FormData();
            formData.append('attempt_id', attemptId);
            formData.append('frame', frame, 'liveness.jpg');
            const data = await post(root.dataset.frameUrl, formData);

            if (data.status === 'passed') {
                stopProcessing();
                challengeStep.textContent = 'Liveness lolos';
                instruction.textContent = 'Mengenali identitas…';
                challengeTimer.textContent = 'Memeriksa wajah';
                speakInstruction('Verifikasi gerakan berhasil. Mengenali identitas.');
                setMessage(data.message);
                await complete();
                return;
            }

            activateChallenge(data);
        } catch (error) {
            stopProcessing();
            instruction.textContent = 'Verifikasi dihentikan';
            challengeTimer.textContent = 'Tidak ada verifikasi aktif';
            setMessage(error.message, true);
            speakInstruction(`Verifikasi dihentikan. ${error.message}`, true);
            startButton.textContent = 'Coba Lagi';
            startButton.disabled = false;
        } finally {
            isProcessingFrame = false;
        }
    };

    const initializeCamera = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            video.srcObject = stream;
            await video.play();
            cameraStatus.textContent = 'Kamera siap. Pastikan wajah terlihat jelas.';
            startButton.disabled = false;
        } catch (error) {
            cameraStatus.textContent = 'Kamera tidak tersedia. Izinkan akses kamera lalu muat ulang halaman.';
            setMessage(error.message, true);
        }
    };

    startButton.addEventListener('click', async () => {
        if (startButton.dataset.completed === 'true') {
            window.location.assign(root.dataset.homeUrl);
            return;
        }

        stopProcessing();
        startButton.disabled = true;
        lastSpokenInstruction = null;
        challengeNumber = null;
        challengeMode = null;
        instruction.textContent = 'Menyiapkan verifikasi…';
        challengeTimer.textContent = 'Menunggu kamera';
        setMessage('Mohon tunggu.');
        // Dipanggil langsung di dalam klik agar browser HP mengizinkan suara berikutnya.
        speakInstruction('Memulai verifikasi.', true);

        try {
            const data = await post(root.dataset.startUrl, new FormData());
            attemptId = data.attempt_id;
            activateChallenge(data, true);
        } catch (error) {
            instruction.textContent = 'Tidak dapat memulai verifikasi';
            challengeTimer.textContent = 'Tidak ada verifikasi aktif';
            setMessage(error.message, true);
            speakInstruction(`Tidak dapat memulai verifikasi. ${error.message}`, true);
            startButton.disabled = false;
        }
    });

    initializeCamera();
});
