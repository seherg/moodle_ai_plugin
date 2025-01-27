import sys
import json
import base64
import joblib

def main():
    # JSON çıktısı için standart format
    result = {
        "status": "error",
        "message": "Bilinmeyen hata"
    }

    try:
        # Argüman kontrolü
        if len(sys.argv) < 2:
            result["message"] = "Soru gereklidir"
            print(json.dumps(result, ensure_ascii=False))
            sys.exit(1)

        # Soruyu çöz
        try:
            question = base64.b64decode(sys.argv[1].encode('utf-8')).decode('utf-8')
        except:
            question = sys.argv[1]

        # Modelleri yükle
        current_dir = sys.path[0]
        rf_model = joblib.load(f'{current_dir}/rf_model.pkl')
        tfidf_vectorizer = joblib.load(f'{current_dir}/tfidf_vectorizer.pkl')
        label_encoder = joblib.load(f'{current_dir}/label_encoder.pkl')

        # Tahmin yap
        question_tfidf = tfidf_vectorizer.transform([question])
        prediction = rf_model.predict(question_tfidf)
        predicted_label = label_encoder.inverse_transform(prediction)[0]

        # Başarılı sonuç
        result = {
            "status": "success", 
            "predicted_label": predicted_label
        }

        print(json.dumps(result, ensure_ascii=False))

    except Exception as e:
        # Herhangi bir hatada detaylı bilgi
        result["message"] = str(e)
        print(json.dumps(result, ensure_ascii=False))

if __name__ == "__main__":
    main()