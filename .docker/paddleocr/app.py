import os
import json
import tempfile

from flask import Flask, jsonify, request
from paddleocr import PaddleOCR


app = Flask(__name__)
ocr = PaddleOCR(lang="en", use_angle_cls=False, show_log=False)


def extract_text(result):
    """Support both the legacy and the current PaddleOCR result shape."""
    texts = []

    if hasattr(result, "json"):
        payload = result.json
        if callable(payload):
            payload = payload()
        if isinstance(payload, str):
            payload = json.loads(payload)
        return extract_text(payload)

    if isinstance(result, dict):
        texts.extend(result.get("rec_texts", []))
        return texts

    if isinstance(result, list):
        for item in result:
            if isinstance(item, dict):
                texts.extend(item.get("rec_texts", []))
            elif isinstance(item, list) and len(item) >= 2:
                texts.append(item[1][0])

    return texts


@app.get("/health")
def health():
    return jsonify({"status": "ok"})


@app.post("/ocr")
def recognize():
    image = request.files.get("image")
    if image is None:
        return jsonify({"error": "image is required"}), 400

    suffix = os.path.splitext(image.filename or "image.jpg")[1] or ".jpg"
    temporary_path = None
    try:
        with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as temporary:
            image.save(temporary)
            temporary_path = temporary.name

        result = ocr.ocr(temporary_path, cls=False)

        texts = []
        items = [result] if isinstance(result, dict) or hasattr(result, "json") else result
        for item in items:
            texts.extend(extract_text(item))

        return jsonify({"text": "\n".join(str(text) for text in texts if text)})
    except Exception as exception:
        app.logger.exception("PaddleOCR recognition failed")
        return jsonify({"error": str(exception)}), 500
    finally:
        if temporary_path:
            try:
                os.unlink(temporary_path)
            except FileNotFoundError:
                pass


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8000)
