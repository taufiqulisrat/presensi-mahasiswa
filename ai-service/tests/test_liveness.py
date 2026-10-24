import unittest
from unittest.mock import patch

import numpy as np

from main import (
    CHALLENGE_TIMEOUT_SECONDS,
    LivenessStartPayload,
    LivenessState,
    challenge_response,
    frame_result,
    liveness_sessions,
    start_liveness,
)


class LivenessChallengeTest(unittest.TestCase):
    def test_liveness_starts_with_four_automatic_photos_then_blink_video(self) -> None:
        liveness_sessions.clear()

        result = start_liveness(LivenessStartPayload(attendance_session_id=1))

        self.assertEqual(
            ["turn_right", "look_up", "look_down", "turn_left", "blink"],
            result["challenges"],
        )
        self.assertEqual("snapshot", result["challenge_mode"])
        liveness_sessions.clear()

    def test_snapshot_advances_after_automatic_capture_without_face_detection(self) -> None:
        state = LivenessState(
            challenges=["turn_right", "blink"],
        )

        with patch("main.engine") as face_engine:
            result = frame_result(state, np.zeros((10, 10, 3), dtype=np.uint8))

        self.assertEqual(0, CHALLENGE_TIMEOUT_SECONDS)
        self.assertEqual("continue", result["status"])
        self.assertEqual("Kedipkan mata sekali", result["instruction"])
        self.assertEqual("Foto otomatis berhasil diambil.", result["message"])
        self.assertEqual(2, result["challenge_number"])
        self.assertEqual("video", result["challenge_mode"])
        self.assertEqual(0, result["challenge_timeout_seconds"])
        self.assertEqual(1, state.current_index)
        face_engine.assert_not_called()

    def test_blink_challenge_uses_video_mode(self) -> None:
        state = LivenessState(
            challenges=["blink"],
            baseline_yaw=0.0,
            baseline_pitch=0.0,
            baseline_ear=0.3,
        )

        result = challenge_response(state)

        self.assertEqual("video", result["challenge_mode"])
        self.assertEqual("Kedipkan mata sekali", result["instruction"])
