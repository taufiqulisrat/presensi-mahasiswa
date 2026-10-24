"""Local AI service for face enrollment, liveness, and identification.

Frames are processed in memory only. Laravel owns persistence of embeddings and
attendance records; this service does not write raw images or biometric data to disk.
"""

from __future__ import annotations

import json
import os
import time
import uuid
from io import BytesIO
from dataclasses import dataclass, field
from functools import lru_cache
from typing import Any

import cv2
import mediapipe as mp
import numpy as np
import torch
from facenet_pytorch import InceptionResnetV1, MTCNN
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from PIL import Image

FACE_DISTANCE_THRESHOLD = float(os.getenv("FACE_DISTANCE_THRESHOLD", "0.85"))
CHALLENGE_TIMEOUT_SECONDS = int(os.getenv("CHALLENGE_TIMEOUT_SECONDS", "0"))
LIVENESS_SESSION_TTL_SECONDS = int(os.getenv("LIVENESS_SESSION_TTL_SECONDS", "0"))
ALLOWED_ORIGINS = [origin.strip() for origin in os.getenv("ALLOWED_ORIGINS", "http://localhost,http://127.0.0.1").split(",")]

SNAPSHOT_CHALLENGES = {"turn_left", "turn_right", "look_up", "look_down"}
INSTRUCTIONS = {
    "turn_left": "Arahkan wajah ke kiri",
    "turn_right": "Arahkan wajah ke kanan",
    "look_up": "Arahkan wajah ke atas",
    "look_down": "Arahkan wajah ke bawah",
    "blink": "Kedipkan mata sekali",
}
LEFT_EYE = [33, 160, 158, 133, 153, 144]
RIGHT_EYE = [362, 385, 387, 263, 373, 380]
HEAD_POINTS = [1, 152, 33, 263, 61, 291]
MODEL_POINTS = np.array(
    [
        (0.0, 0.0, 0.0),
        (0.0, -63.6, -12.5),
        (-43.3, 32.7, -26.0),
        (43.3, 32.7, -26.0),
        (-28.9, -28.9, -24.1),
        (28.9, -28.9, -24.1),
    ],
    dtype=np.float64,
)


class LivenessStartPayload(BaseModel):
    attendance_session_id: int


@dataclass
class LivenessState:
    challenges: list[str]
    current_index: int = 0
    expires_at: float | None = field(
        default_factory=lambda: time.time() + LIVENESS_SESSION_TTL_SECONDS
        if LIVENESS_SESSION_TTL_SECONDS > 0
        else None,
    )
    baseline_yaw: float | None = None
    baseline_pitch: float | None = None
    baseline_ear: float | None = None
    blink_closed: bool = False
    challenge_started_at: float = field(default_factory=time.time)


class FaceEngine:
    """CPU inference pipeline shared by all local API requests."""

    def __init__(self) -> None:
        self.device = torch.device("cuda:0" if torch.cuda.is_available() else "cpu")
        self.mtcnn = MTCNN(image_size=160, margin=20, keep_all=False, device=self.device)
        self.resnet = InceptionResnetV1(pretrained="vggface2").eval().to(self.device)
        self.face_mesh = mp.solutions.face_mesh.FaceMesh(
            static_image_mode=False,
            max_num_faces=1,
            refine_landmarks=True,
            min_detection_confidence=0.6,
            min_tracking_confidence=0.6,
        )

    def embedding(self, image: Image.Image) -> tuple[list[float], float]:
        aligned_face, probability = self.mtcnn(image, return_prob=True)

        if aligned_face is None or probability is None:
            raise ValueError("Wajah tidak terdeteksi. Hadapkan satu wajah ke kamera.")

        with torch.inference_mode():
            vector = self.resnet(aligned_face.unsqueeze(0).to(self.device)).cpu().numpy()[0]

        return vector.astype(float).tolist(), float(probability)

    def landmarks(self, image: np.ndarray) -> tuple[float, float, float]:
        height, width = image.shape[:2]
        rgb_image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
        result = self.face_mesh.process(rgb_image)

        if not result.multi_face_landmarks:
            raise ValueError("Wajah tidak terdeteksi. Posisikan wajah di tengah kamera.")

        landmarks = result.multi_face_landmarks[0].landmark
        yaw, pitch = self._head_pose(landmarks, width, height)
        ear = (self._eye_aspect_ratio(landmarks, LEFT_EYE, width, height) + self._eye_aspect_ratio(landmarks, RIGHT_EYE, width, height)) / 2

        return yaw, pitch, ear

    @staticmethod
    def _eye_aspect_ratio(landmarks: Any, indices: list[int], width: int, height: int) -> float:
        points = np.array([(landmarks[index].x * width, landmarks[index].y * height) for index in indices], dtype=np.float64)
        vertical_one = np.linalg.norm(points[1] - points[5])
        vertical_two = np.linalg.norm(points[2] - points[4])
        horizontal = np.linalg.norm(points[0] - points[3])

        return float((vertical_one + vertical_two) / (2.0 * horizontal)) if horizontal else 0.0

    @staticmethod
    def _head_pose(landmarks: Any, width: int, height: int) -> tuple[float, float]:
        image_points = np.array(
            [(landmarks[index].x * width, landmarks[index].y * height) for index in HEAD_POINTS],
            dtype=np.float64,
        )
        focal_length = width
        camera_matrix = np.array(
            [[focal_length, 0, width / 2], [0, focal_length, height / 2], [0, 0, 1]],
            dtype=np.float64,
        )
        success, rotation_vector, _ = cv2.solvePnP(MODEL_POINTS, image_points, camera_matrix, np.zeros((4, 1)), flags=cv2.SOLVEPNP_ITERATIVE)

        if not success:
            raise ValueError("Arah kepala belum dapat dihitung.")

        rotation_matrix, _ = cv2.Rodrigues(rotation_vector)
        angles, _, _, _, _, _ = cv2.RQDecomp3x3(rotation_matrix)
        pitch, yaw, _ = angles

        return float(yaw), float(pitch)


@lru_cache(maxsize=1)
def engine() -> FaceEngine:
    return FaceEngine()


app = FastAPI(title="Presensi Aman Face AI", version="0.1.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=ALLOWED_ORIGINS,
    allow_credentials=False,
    allow_methods=["POST", "GET"],
    allow_headers=["*"],
)
liveness_sessions: dict[str, LivenessState] = {}


def decode_image(contents: bytes) -> tuple[Image.Image, np.ndarray]:
    try:
        image = Image.open(BytesIO(contents)).convert("RGB")
    except (OSError, ValueError) as exception:
        raise HTTPException(status_code=422, detail="File frame bukan gambar yang valid.") from exception

    return image, cv2.cvtColor(np.array(image), cv2.COLOR_RGB2BGR)


async def image_from_upload(frame: UploadFile) -> tuple[Image.Image, np.ndarray]:
    if frame.content_type not in {"image/jpeg", "image/png"}:
        raise HTTPException(status_code=422, detail="Frame harus berupa gambar JPEG atau PNG.")

    contents = await frame.read()

    if not contents:
        raise HTTPException(status_code=422, detail="Frame kamera kosong.")

    return decode_image(contents)


def cleanup_expired_sessions() -> None:
    now = time.time()
    expired_ids = [
        session_id
        for session_id, state in liveness_sessions.items()
        if state.expires_at is not None and state.expires_at < now
    ]

    for session_id in expired_ids:
        liveness_sessions.pop(session_id, None)


def state_or_404(session_id: str) -> LivenessState:
    cleanup_expired_sessions()
    state = liveness_sessions.get(session_id)

    if state is None:
        raise HTTPException(status_code=422, detail="Sesi liveness tidak ditemukan atau sudah kedaluwarsa.")

    return state


def action_completed(state: LivenessState, yaw: float, pitch: float, ear: float) -> bool:
    action = state.challenges[state.current_index]

    if action != "blink":
        return True

    close_threshold = state.baseline_ear * 0.72
    open_threshold = state.baseline_ear * 0.82

    if ear < close_threshold:
        state.blink_closed = True

    return state.blink_closed and ear > open_threshold


def incomplete_action_message(state: LivenessState) -> str:
    return "Kedipan belum terdeteksi. Kedipkan mata perlahan sekali lagi."


def challenge_response(
    state: LivenessState,
    status: str = "continue",
    message: str | None = None,
) -> dict[str, Any]:
    response: dict[str, Any] = {
        "status": status,
        "instruction": INSTRUCTIONS[state.challenges[state.current_index]],
        "challenge_number": state.current_index + 1,
        "challenge_total": len(state.challenges),
        "challenge_mode": "snapshot"
        if state.challenges[state.current_index] in SNAPSHOT_CHALLENGES
        else "video",
        "challenge_timeout_seconds": CHALLENGE_TIMEOUT_SECONDS,
    }

    if message is not None:
        response["message"] = message

    return response


def retry_current_challenge(state: LivenessState) -> None:
    state.blink_closed = False
    state.challenge_started_at = time.time()


def frame_result(state: LivenessState, image: np.ndarray) -> dict[str, Any]:
    if (
        CHALLENGE_TIMEOUT_SECONDS > 0
        and time.time() - state.challenge_started_at > CHALLENGE_TIMEOUT_SECONDS
    ):
        retry_current_challenge(state)

        return challenge_response(
            state,
            status="retry",
            message=f"Waktu {CHALLENGE_TIMEOUT_SECONDS} detik telah habis. Ulangi arahan yang sama.",
        )

    if state.challenges[state.current_index] in SNAPSHOT_CHALLENGES:
        state.current_index += 1

        if state.current_index == len(state.challenges):
            return {"status": "passed"}

        retry_current_challenge(state)

        return challenge_response(state, message="Foto otomatis berhasil diambil.")

    try:
        yaw, pitch, ear = engine().landmarks(image)
    except ValueError as exception:
        return challenge_response(state, message=str(exception))

    if state.baseline_yaw is None:
        state.baseline_yaw = yaw
        state.baseline_pitch = pitch
        state.baseline_ear = ear

        return challenge_response(state, message="Wajah terdeteksi. Kedipkan mata perlahan sekali.")

    if not action_completed(state, yaw, pitch, ear):
        return challenge_response(state, message=incomplete_action_message(state))

    state.current_index += 1

    if state.current_index == len(state.challenges):
        return {"status": "passed"}

    retry_current_challenge(state)

    return challenge_response(state)


@app.get("/api/health")
def health() -> dict[str, str]:
    return {"status": "ok", "device": str(engine().device)}


@app.post("/api/liveness/start")
def start_liveness(_: LivenessStartPayload) -> dict[str, Any]:
    cleanup_expired_sessions()
    session_id = str(uuid.uuid4())
    challenges = ["turn_right", "look_up", "look_down", "turn_left", "blink"]
    liveness_sessions[session_id] = LivenessState(challenges=challenges)

    return {
        "session_id": session_id,
        "challenges": challenges,
        "instruction": INSTRUCTIONS[challenges[0]],
        "challenge_mode": "snapshot",
        "challenge_timeout_seconds": CHALLENGE_TIMEOUT_SECONDS,
    }


@app.post("/api/liveness/frame")
async def process_liveness_frame(
    ai_session_id: str = Form(...),
    frame: UploadFile = File(...),
) -> dict[str, Any]:
    state = state_or_404(ai_session_id)
    _, image = await image_from_upload(frame)

    return frame_result(state, image)


@app.post("/api/enrollment/frame")
async def enroll_frame(
    student_id: int = Form(...),
    pose: str = Form(...),
    frame: UploadFile = File(...),
) -> dict[str, Any]:
    if pose not in {"front", "left", "right", "up", "down"}:
        raise HTTPException(status_code=422, detail="Pose pendaftaran tidak valid.")

    image, _ = await image_from_upload(frame)

    try:
        embedding, quality_score = engine().embedding(image)
    except ValueError as exception:
        return {"accepted": False, "message": str(exception)}

    return {
        "accepted": True,
        "student_id": student_id,
        "pose": pose,
        "model": "facenet-vggface2",
        "quality_score": quality_score,
        "embedding": embedding,
    }


@app.post("/api/recognition/identify")
async def identify_face(
    attendance_session_id: int = Form(...),
    candidates_json: str = Form(...),
    frame: UploadFile = File(...),
) -> dict[str, Any]:
    del attendance_session_id
    image, _ = await image_from_upload(frame)

    try:
        candidates = json.loads(candidates_json)
        current_embedding, _ = engine().embedding(image)
    except (ValueError, json.JSONDecodeError) as exception:
        return {"recognized": False, "message": str(exception)}

    if not candidates:
        return {"recognized": False, "message": "Belum ada embedding pendaftaran."}

    query_embedding = np.asarray(current_embedding, dtype=np.float32)
    best_candidate: dict[str, Any] | None = None
    best_distance = float("inf")

    for candidate in candidates:
        candidate_embedding = np.asarray(candidate["embedding"], dtype=np.float32)

        if candidate_embedding.shape != query_embedding.shape:
            continue

        distance = float(np.linalg.norm(query_embedding - candidate_embedding))

        if distance < best_distance:
            best_distance = distance
            best_candidate = candidate

    if best_candidate is None or best_distance > FACE_DISTANCE_THRESHOLD:
        return {"recognized": False, "message": "Wajah tidak cocok dengan data pendaftaran.", "distance": best_distance}

    return {
        "recognized": True,
        "student_id": int(best_candidate["student_id"]),
        "distance": best_distance,
    }
