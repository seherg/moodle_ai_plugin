<?php
require('../../config.php');
global $CFG, $DB, $PAGE, $OUTPUT, $USER;

header('Content-Type: text/html; charset=utf-8');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);

$PAGE->set_url('/local/ai_yorumu/index.php', array('courseid' => $courseid));
$PAGE->set_context($context);
$PAGE->set_title('Yapay Zeka ile Analiz');
$PAGE->set_heading($course->fullname);

// Etiket-hafta JSON dosyasını oku
$json_file_path = $CFG->dirroot . '/local/ai_yorumu/etiket_hafta.json';
$etiket_hafta = json_decode(file_get_contents($json_file_path), true);

// YZ tahmini için gelişmiş fonksiyon
function get_ai_prediction($question_text, $question_name) {
    global $CFG;

    if (empty($question_text)) {
        return 'Soru metni boş';
    }

    try {
        $python_path = 'C:\\Users\\shrgu\\AppData\\Local\\Programs\\Python\\Python313\\python.exe';
        if (!file_exists($python_path)) {
            error_log("Python yolu bulunamadı: $python_path");
            return 'Python yolu bulunamadı';
        }

        $script_path = str_replace('\\', '/', $CFG->dirroot . '/local/ai_yorumu/predict.py');
        
        // Soru metnini temizle ve kodla
        $cleaned_text = strip_tags($question_text);
        $cleaned_text = preg_replace('/[\r\n\t]+/', ' ', $cleaned_text);
        $cleaned_text = trim($cleaned_text);
        
        $encoded_text = base64_encode($cleaned_text);

        $command = sprintf(
            '"%s" "%s" %s 2>&1',
            $python_path,
            $script_path,
            escapeshellarg($encoded_text)
        );
        
        $output = shell_exec($command);
        $result = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON hatası: " . json_last_error_msg());
        }

        return isset($result['predicted_label']) ? $result['predicted_label'] : 'Tahmin sonucu bulunamadı';

    } catch (Exception $e) {
        error_log("Tahmin hatası: " . $e->getMessage());
        return "Hata: " . $e->getMessage();
    }
}

// CSS stil tanımlamaları
echo $OUTPUT->header();
?>
<style>
.goto-week-btn, 
.btn-primary, 
.btn-sm {
    padding: 5px 10px;
    margin-left: 10px;
    background-color: #0056b3;
    color: white !important;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none !important;
    display: inline-block;
    font-size: 0.9rem;
    line-height: 1.5;
}
.goto-week-btn:hover, 
.btn-primary:hover, 
.btn-sm:hover {
    background-color: #003d82;
    color: white !important;
    text-decoration: none !important;
}
.quiz-details {
    margin-bottom: 2rem;
}
.topic-analysis {
    margin-top: 2rem;
}
.badge {
    font-size: 0.9em;
    padding: 8px 12px;
}
.topic-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    margin-bottom: 5px;
    background-color: #f8f9fa;
    border-radius: 4px;
}
.topic-name {
    flex-grow: 1;
}
.topic-stats {
    margin-left: 15px;
    white-space: nowrap;
}
</style>

<?php
echo '<div class="container">';

try {
    // Tüm sınavları getir
    $quizzes = $DB->get_records_sql(
        "SELECT q.*, cm.id as cmid 
         FROM {quiz} q
         JOIN {course_modules} cm ON cm.instance = q.id
         JOIN {modules} m ON m.id = cm.module
         WHERE m.name = 'quiz' AND cm.course = ?
         ORDER BY q.timeopen DESC",
        [$courseid]
    );

    if ($quizzes) {
        $overall_topic_analysis = array();
        $total_wrong_questions = 0;
        $has_any_attempt = false;

        // Her sınav için detayları göster
        foreach ($quizzes as $quiz) {
            echo '<div class="card quiz-details mb-4">';
            echo '<div class="card-header bg-primary text-white">';
            echo '<h3 class="mb-0">' . htmlspecialchars($quiz->name) . '</h3>';
            echo '</div>';
            echo '<div class="card-body">';

            // Sınava girip girmediğini kontrol et
            $attempts = $DB->get_records('quiz_attempts', [
                'quiz' => $quiz->id,
                'userid' => $USER->id,
                'state' => 'finished'
            ]);

            if (empty($attempts)) {
                echo '<div class="alert alert-warning">Bu sınava henüz girmemişsiniz.</div>';
                echo '</div></div>';
                continue;
            }

            $has_any_attempt = true;

            // Yanlış cevapları getir
            $wrong_answers = $DB->get_records_sql(
                "SELECT DISTINCT qa.*, q.name AS question_name, 
                        q.questiontext AS question_text,
                        qa.responsesummary AS student_answer,
                        qa.rightanswer AS correct_answer
                 FROM {quiz_attempts} qza
                 JOIN {question_attempts} qa ON qa.questionusageid = qza.uniqueid
                 JOIN {question} q ON q.id = qa.questionid
                 WHERE qza.quiz = ? AND qza.userid = ? 
                 AND qa.rightanswer != qa.responsesummary
                 AND qza.state = 'finished'
                 ORDER BY qza.attempt DESC, qa.slot ASC",
                [$quiz->id, $USER->id]
            );

            if ($wrong_answers) {
                $total_wrong_questions += count($wrong_answers);

                echo '<div class="table-responsive">';
                echo '<table class="table table-striped table-bordered">';
                echo '<thead class="thead-dark">';
                echo '<tr>';
                echo '<th>Soru</th>';
                echo '<th>Doğru Cevap</th>';
                echo '<th>Sizin Cevabınız</th>';
                echo '<th>Konu</th>';
                echo '<th>İlgili Hafta</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                foreach ($wrong_answers as $answer) {
                    $ai_prediction = get_ai_prediction($answer->question_text, $answer->question_name);
                    
                    // Genel analiz için topla
                    if (!isset($overall_topic_analysis[$ai_prediction])) {
                        $overall_topic_analysis[$ai_prediction] = 0;
                    }
                    $overall_topic_analysis[$ai_prediction]++;
                
                    $week_number = isset($etiket_hafta[$ai_prediction]) ? $etiket_hafta[$ai_prediction] : 'Bilinmiyor';
                    $section_url = new moodle_url('/course/view.php', [
                        'id' => $courseid,
                        'section' => $week_number
                    ]);
                
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($answer->question_name) . '</td>';
                    echo '<td>' . htmlspecialchars($answer->correct_answer) . '</td>';
                    echo '<td>' . htmlspecialchars($answer->student_answer) . '</td>';
                    echo '<td>' . htmlspecialchars($ai_prediction) . '</td>';
                    echo '<td>';
                    if ($week_number !== 'Bilinmiyor') {
                        echo '<a href="' . $section_url . '" class="goto-week-btn">Hafta ' . $week_number . '</a>';
                    } else {
                        echo '<span class="text-danger">Konu Bulunamadı</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }                

                echo '</tbody>';
                echo '</table>';
                echo '</div>';

            } else {
                echo '<div class="alert alert-success">Tebrikler! Bu sınavdaki tüm soruları doğru cevapladınız.</div>';
            }

            echo '</div>';
            echo '</div>';
        }

        // Genel konu bazlı analiz özetini göster
        if ($has_any_attempt && !empty($overall_topic_analysis)) {
            echo '<div class="card topic-analysis">';
            echo '<div class="card-header bg-primary text-white">';
            echo '<h3 class="mb-0">Konu Bazlı Analiz Özeti</h3>';
            echo '</div>';
            echo '<div class="card-body">';
            
            arsort($overall_topic_analysis);
            
            foreach ($overall_topic_analysis as $topic => $count) {
                $percentage = ($count / $total_wrong_questions) * 100;
                $week = isset($etiket_hafta[$topic]) ? $etiket_hafta[$topic] : 'Bilinmiyor';
                
                echo '<div class="topic-item">';
                echo '<span class="topic-name">' . htmlspecialchars($topic) . '</span>';
                
                if ($week !== 'Bilinmiyor') {
                    $section_url = new moodle_url('/course/view.php', [
                        'id' => $courseid,
                        'section' => $week
                    ]);
                    echo '<a href="' . $section_url . '" class="goto-week-btn">Hafta ' . $week . '</a>';
                }
                
                echo '<span class="topic-stats badge bg-primary">' . 
                     number_format($percentage, 1) . '% (' . $count . ' soru)</span>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        
    } else {
        echo '<div class="alert alert-info">Bu derste henüz sınav bulunmamaktadır.</div>';
    }

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Hata: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '</div>';
echo $OUTPUT->footer();