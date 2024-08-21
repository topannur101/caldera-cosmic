import cv2
from PyQt6.QtGui import QImage, QPixmap
from PyQt6.QtCore import Qt

class CameraManager:
    def __init__(self, config):
        self.config = config

    def capture_photo(self):
        cap = cv2.VideoCapture(self.config['capture']['camera_index'])
        ret, frame = cap.read()
        cap.release()

        if ret:
            return self.process_image(frame)
        else:
            return None

    def process_image(self, frame):
        rgb_image = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        h, w, ch = rgb_image.shape
        bytes_per_line = ch * w
        qt_image = QImage(rgb_image.data, w, h, bytes_per_line, QImage.Format.Format_RGB888)
        return QPixmap.fromImage(qt_image)

    def scale_image(self, pixmap, size):
        return pixmap.scaled(size, Qt.AspectRatioMode.KeepAspectRatio, Qt.TransformationMode.SmoothTransformation)
