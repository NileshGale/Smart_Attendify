"""
Attendify Face Engine — PythonAnywhere Deployment
Flask API for face encoding generation and group photo matching.

Endpoints:
  POST /generate-encoding  — Generate 128-D face encoding from a single photo
  POST /match-faces        — Match faces in group photos against known encodings
  GET  /health             — Health check
"""

from flask import Flask, request, jsonify
import face_recognition
import numpy as np
import base64
import io
import os

app = Flask(__name__)

# ── Security: API Key ─────────────────────────────────────────────────────────
# Change this to a strong secret key and keep it same in your PHP db_config.php
API_KEY = os.environ.get('FACE_API_KEY', 'attendify-face-api-secret-2024')

def verify_api_key():
    """Check X-API-Key header matches our secret."""
    return request.headers.get('X-API-Key') == API_KEY


def decode_base64_image(b64_string):
    """Convert base64 string to a numpy image array for face_recognition."""
    # Remove data URI prefix if present (e.g., "data:image/jpeg;base64,")
    if ',' in b64_string:
        b64_string = b64_string.split(',', 1)[1]
    
    image_data = base64.b64decode(b64_string)
    image = face_recognition.load_image_file(io.BytesIO(image_data))
    return image


# ══════════════════════════════════════════════════════════════════════════════
# ENDPOINT 1: Generate Face Encoding
# Called during student registration to create the 128-D face vector
# ══════════════════════════════════════════════════════════════════════════════
@app.route('/generate-encoding', methods=['POST'])
def generate_encoding():
    if not verify_api_key():
        return jsonify({"success": False, "error": "Unauthorized"}), 401
    
    try:
        data = request.get_json()
        photo_b64 = data.get('photo', '')
        
        if not photo_b64:
            return jsonify({"success": False, "message": "No photo provided"})
        
        # Decode and load image
        image = decode_base64_image(photo_b64)
        
        # Detect faces in the image
        face_locations = face_recognition.face_locations(image, model="hog")
        
        if len(face_locations) == 0:
            return jsonify({
                "success": False, 
                "message": "Please upload a clear front-facing photo for attendance verification"
            })
        
        if len(face_locations) > 1:
            return jsonify({
                "success": False, 
                "message": "Multiple faces detected. Please upload a photo with only your face"
            })
        
        # Generate the 128-D encoding
        encodings = face_recognition.face_encodings(image, face_locations)
        
        return jsonify({
            "success": True,
            "encoding": encodings[0].tolist()  # Convert numpy array to list for JSON
        })
        
    except Exception as e:
        return jsonify({"success": False, "message": f"Processing error: {str(e)}"})


# ══════════════════════════════════════════════════════════════════════════════
# ENDPOINT 2: Match Faces in Group Photos
# Called when teacher uploads group photos for attendance marking
# ══════════════════════════════════════════════════════════════════════════════
@app.route('/match-faces', methods=['POST'])
def match_faces():
    if not verify_api_key():
        return jsonify({"success": False, "error": "Unauthorized"}), 401
    
    try:
        data = request.get_json()
        group_photos = data.get('photos', [])          # Array of base64 images
        known_students = data.get('students', [])       # [{id, name, reg_id, encoding}]
        
        if not group_photos:
            return jsonify({"success": False, "message": "No group photos provided"})
        
        if not known_students:
            return jsonify({"success": False, "message": "No student encodings provided"})
        
        # Prepare known encodings as numpy arrays
        known_encodings = []
        valid_students = []
        
        for student in known_students:
            if student.get('encoding'):
                known_encodings.append(np.array(student['encoding']))
                valid_students.append(student)
        
        if not known_encodings:
            return jsonify({"success": False, "message": "No valid face encodings found for enrolled students"})
        
        # ── Process each group photo ──────────────────────────────────────────
        matched = {}          # {student_id: {id, name, reg_id, confidence}}
        total_faces_found = 0
        
        for photo_b64 in group_photos:
            try:
                image = decode_base64_image(photo_b64)
                
                # Detect all faces in this group photo (HOG is faster, good enough)
                face_locations = face_recognition.face_locations(image, model="hog")
                face_encodings = face_recognition.face_encodings(image, face_locations)
                
                total_faces_found += len(face_encodings)
                
                # Compare each detected face against known students
                for face_enc in face_encodings:
                    distances = face_recognition.face_distance(known_encodings, face_enc)
                    best_idx = int(distances.argmin())
                    best_distance = float(distances[best_idx])
                    
                    # Threshold: <= 0.6 is a match (lower = more confident)
                    if best_distance <= 0.6:
                        student = valid_students[best_idx]
                        sid = student['id']
                        confidence = round((1 - best_distance) * 100, 1)
                        
                        # Deduplication: keep highest confidence match
                        if sid not in matched or confidence > matched[sid]['confidence']:
                            matched[sid] = {
                                "id": sid,
                                "name": student['name'],
                                "reg_id": student['reg_id'],
                                "confidence": confidence
                            }
                            
            except Exception as photo_err:
                # Skip this photo if it fails, continue with others
                continue
        
        # Sort by confidence (highest first)
        students_list = sorted(matched.values(), key=lambda x: x['confidence'], reverse=True)
        
        return jsonify({
            "success": True,
            "total_faces_detected": total_faces_found,
            "total_matched": len(students_list),
            "students": students_list
        })
        
    except Exception as e:
        return jsonify({"success": False, "message": f"Processing error: {str(e)}"})


# ══════════════════════════════════════════════════════════════════════════════
# HEALTH CHECK
# ══════════════════════════════════════════════════════════════════════════════
@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        "status": "ok",
        "service": "Attendify Face Engine",
        "version": "1.0"
    })


# ── Local development only ────────────────────────────────────────────────────
if __name__ == '__main__':
    app.run(debug=True, port=5000)
