import pandas as pd
import joblib
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report, accuracy_score
from sklearn.preprocessing import LabelEncoder

# Load dataset
file_path_combined = 'temizlenmis_dosya_cleaned.csv'
data = pd.read_csv(file_path_combined)

# Clean missing values
data_cleaned = data.dropna(subset=['Soru', 'Etiket'])

# Combine labels with less than 2 samples into "Diğer"
label_counts = data_cleaned['Etiket'].value_counts()
labels_to_combine = label_counts[label_counts < 2].index
data_cleaned['Etiket'] = data_cleaned['Etiket'].replace(labels_to_combine, "Diğer")

# Prepare features (X) and labels (y)
X = data_cleaned['Soru']
y = data_cleaned['Etiket']

# Convert text data to TF-IDF features
tfidf_vectorizer = TfidfVectorizer(max_features=5000)
X_tfidf = tfidf_vectorizer.fit_transform(X)

# Encode labels
label_encoder = LabelEncoder()
y_encoded = label_encoder.fit_transform(y)

# Split data into training and testing sets
X_train, X_test, y_train, y_test = train_test_split(X_tfidf, y_encoded, test_size=0.2, random_state=42, stratify=y_encoded)

# Random Forest model
rf_model = RandomForestClassifier(n_estimators=100, random_state=42)
rf_model.fit(X_train, y_train)

# Predict and evaluate
rf_pred = rf_model.predict(X_test)
rf_accuracy = accuracy_score(y_test, rf_pred)
print(f"Random Forest Model Accuracy: {rf_accuracy}")
print("\nClassification Report:")
print(classification_report(y_test, rf_pred, target_names=label_encoder.classes_))

# Save models
joblib.dump(rf_model, 'rf_model.pkl')
joblib.dump(tfidf_vectorizer, 'tfidf_vectorizer.pkl')
joblib.dump(label_encoder, 'label_encoder.pkl')

# Prediction function
def predict_question_label(question):
    question_tfidf = tfidf_vectorizer.transform([question])
    prediction = rf_model.predict(question_tfidf)
    return label_encoder.inverse_transform(prediction)[0]

# Example usage
example_question = "Aşağıdakilerden hangisi python ile ilgili değildir"
print("\nÖrnek Soru Tahmini:")
print(predict_question_label(example_question))