# moodle_ai_plugin
 moodle ai plugin
# Moodle Yapay Zeka Yorumlama Modülü

Bu proje, Moodle platformunda sınavlar sırasında öğrencilerin verdiği cevaplara göre eksik oldukları konuları analiz eden bir eklenti geliştirmek için tasarlanmıştır. Yapay zeka modeli, yanlış verilen cevaplara dayanarak öğrencinin eksik olduğu konuları belirler ve bu konularla ilgili kaynaklara yönlendirme yapar.

## Özellikler
- **Yanlış Cevap Analizi:** Öğrencinin sınavdaki yanlış cevaplarını tespit eder.
- **Konu Analizi:** Yanlış cevaplardan hareketle öğrencinin eksik olduğu konuları yapay zeka ile belirler.
- **Kaynak Yönlendirme:** Her eksik konu için kurs içerisinde ilgili haftaya yönlendirme sağlar.
- **JSON Dosyası ile Esnek Konu-İlgili Hafta Eşleşmesi:** `etiket_hafta.json` dosyasıyla etiketler ve haftalar esnek şekilde eşleştirilebilir.
- **Basit ve Kullanışlı Arayüz:** Moodle'ın varsayılan stil düzeniyle sade ve anlaşılır bir görüntü sağlar.
![image](https://github.com/user-attachments/assets/4d96886a-8137-4849-8592-e5bbc9f5c85a)


![image](https://github.com/user-attachments/assets/a4702d9d-cd92-4825-ba7c-7303e6249daa)

## Kurulum

###  Dosyaları Moodle'a Yükleyin
Eklenti dosyalarını `moodle/local/ai_yorumu` dizinine kopyalayın:
- `index.php`
- `predict.py`
- `etiket_hafta.json`

### Gerekli Ayarları Yapın
- Python 3.13 sürümünün kurulu olduğundan emin olun.
- Kullanılan Python dosyası ve modellerin yolu `predict.py` ve `index.php` içinde doğru ayarlanmalıdır.


### Eklentiyi Moodle'da Etkinleştirin
1. Moodle'da **Eklenti Yönetimi** sayfasına gidin.
2. Yüklemiş olduğunuz bu modülü etkinleştirin.
3. Eklenti etkinleştirildikten sonra, ilgili kurslarda **AI Analiz** sekmesi altında çalışacaktır.

### Kullanım
1. **Sınav:** Öğrencinin sınava girmesi gereklidir. Sınava giren öğrencilerin cevapları analiz edilir.
2. **Yanlış Cevaplar:** Sınavda yanlış verilen cevaplar listelenir.
3. **Konu Analizi:** Yanlış cevaplara göre öğrenciye eksik olduğu konular gösterilir.
4. **Kaynak Yönlendirme:** Her konu için kurs içerisindeki ilgili haftaya yönlendiren bir buton sunulur (örneğin, **"Hafta 2"**).

### Dosya Açıklamaları
- **index.php:** Ana dosya. Moodle'daki sınavların analizini ve eksik konuların gösterimini sağlar.
- **predict.py:** Python tabanlı yapay zeka modeli. Soru metni ile konu analizi yapar.
- **etiket_hafta.json:** Konular ve ilgili haftaların eşleştirildiği yapılandırma dosyası.

### Gereksinimler
- **Moodle Sürümü:** 3.x ve üzeri
- **Python Sürümü:** 3.13 ve üzeri
- **Ek Python Paketleri:** `scikit-learn`, `joblib`, `numpy`
