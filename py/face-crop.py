import os
import cv2
import numpy as np
from PIL import Image

def process_images(input_folder):
    face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
    success_count = 0
    failed_count = 0

    for filename in os.listdir(input_folder):
        if filename.lower().endswith(('.png', '.jpg', '.jpeg', '.gif')):
            file_path = os.path.join(input_folder, filename)
            
            # Open image with PIL to handle transparency
            pil_image = Image.open(file_path)
            if pil_image.mode == 'RGBA':
                # Convert transparent background to white
                background = Image.new('RGBA', pil_image.size, (255, 255, 255))
                pil_image = Image.alpha_composite(background, pil_image).convert('RGB')
            
            # Convert PIL image to OpenCV format
            image = cv2.cvtColor(np.array(pil_image), cv2.COLOR_RGB2BGR)
            
            gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
            faces = face_cascade.detectMultiScale(gray, 1.3, 5)
            
            if len(faces) > 0:
                # Get the largest face
                (x, y, w, h) = max(faces, key=lambda f: f[2] * f[3])
                
                # Calculate padding
                padding_x = min(x, image.shape[1] - (x + w))
                padding_y = min(y, image.shape[0] - (y + h))
                padding = min(padding_x, padding_y)
                
                # Crop image with padding
                crop_img = image[max(0, y-padding):min(image.shape[0], y+h+padding),
                                 max(0, x-padding):min(image.shape[1], x+w+padding)]
                
                # Resize to 320x320 while maintaining aspect ratio
                height, width = crop_img.shape[:2]
                if width > height:
                    new_width = 320
                    new_height = int(height * (320 / width))
                else:
                    new_height = 320
                    new_width = int(width * (320 / height))
                
                resized_img = cv2.resize(crop_img, (new_width, new_height))
                
                # Create a white 320x320 background
                final_img = np.full((320, 320, 3), 255, dtype=np.uint8)
                
                # Paste the resized image onto the center of the background
                x_offset = (320 - new_width) // 2
                y_offset = (320 - new_height) // 2
                final_img[y_offset:y_offset+new_height, x_offset:x_offset+new_width] = resized_img
                
                # Save as JPG
                output_filename = os.path.splitext(filename)[0] + '.jpg'
                cv2.imwrite(os.path.join(input_folder, output_filename), final_img)
                success_count += 1
            else:
                failed_count += 1
    
    print(f"Processing complete. Successes: {success_count}, Failures: {failed_count}")

# Usage
input_folder = 'output'
process_images(input_folder)