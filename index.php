<?php
require('../../config.php');
global $CFG, $DB, $PAGE, $OUTPUT, $USER;

header('Content-Type: text/html; charset=utf-8');
header('Content-Type: application/json; charset=utf-8');

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

// CSS ve JavaScript ekle
echo $OUTPUT->header();
?>
<style>
.goto-week-btn {
    padding: 5px 10px;
    margin-left: 10px;
    background-color: #0056b3;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.goto-week-btn:hover {
    background-color: #003d82;
}
</style>

<?php
echo '<div class="container">';

try {
    $quizzes = $DB->get_records_sql(
        "SELECT q.*, cm.id as cmid 
         FROM {quiz} q
         JOIN {course_modules} cm ON cm.instance = q.id
         JOIN {modules} m ON m.id = cm.module
         WHERE m.name = 'quiz' AND cm.course = ?",
        [$courseid]
    );

    if ($quizzes) {
        foreach ($quizzes as $quiz) {
            echo '<div class="card mb-4">';
            echo '<div class="card-header bg-primary text-white">';
            echo '<h3>' . htmlspecialchars($quiz->name) . '</h3>';
            echo '</div>';
            echo '<div class="card-body">';

            $wrong_answers = $DB->get_records_sql(
                "SELECT qa.*, q.name AS question_name, 
                        q.questiontext AS question_text,
                        qa.responsesummary AS student_answer,
                        qa.rightanswer AS correct_answer
                 FROM {quiz_attempts} qza
                 JOIN {question_attempts} qa ON qa.questionusageid = qza.uniqueid
                 JOIN {question} q ON q.id = qa.questionid
                 WHERE qza.quiz = ? AND qza.userid = ? 
                 AND qa.rightanswer != qa.responsesummary
                 ORDER BY qza.attempt DESC",
                [$quiz->id, $USER->id]
            );

            if ($wrong_answers) {
                $topic_analysis = array();
                
                echo '<div class="table-responsive">';
                echo '<table class="table table-striped table-bordered">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>Soru Etiketi</th>';
                echo '<th>Doğru Cevap</th>';
                echo '<th>Öğrenci Cevabı</th>';
                echo '<th>YZ Analizi</th>';
                echo '<th>İlgili Hafta</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                foreach ($wrong_answers as $answer) {
                    $ai_prediction = get_ai_prediction($answer->question_text, $answer->question_name);
                    $week_number = isset($etiket_hafta[$ai_prediction]) ? $etiket_hafta[$ai_prediction] : 'Bilinmiyor';

                    $section_url = new moodle_url('/course/view.php', [
                        'id' => $courseid,
                        'section' => $week_number
                    ]);

                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($answer->question_name, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($answer->correct_answer, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($answer->student_answer, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($ai_prediction, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>';
                    if ($week_number !== 'Bilinmiyor') {
                        echo '<a href="' . $section_url . '" class="btn btn-primary btn-sm">Hafta ' . $week_number . '</a>';
                    } else {
                        echo '<span class="text-danger">Konu Bulunamadı</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';

                echo '</div>';

                // Konu bazlı analiz özeti
                echo '<div class="mt-4">';
                echo '<h4>Konu Bazlı Analiz Özeti</h4>';
                echo '<ul class="list-group">';
                arsort($topic_analysis);
                foreach ($topic_analysis as $topic => $count) {
                    $percentage = ($count / count($wrong_answers)) * 100;
                    $week = isset($etiket_hafta[$topic]) ? $etiket_hafta[$topic] : 'Bilinmiyor';
                    $section_url = new moodle_url('/course/view.php', [
                        'id' => $courseid,
                        'section' => $week
                    ]);
                    
                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                    echo htmlspecialchars($topic);
                    if ($week !== 'Bilinmiyor') {
                        echo ' <a href="' . $section_url . '" class="goto-week-btn">Hafta ' . $week . '</a>';
                    }
                    echo '<span class="badge bg-primary rounded-pill">' . 
                         number_format($percentage, 1) . '% (' . $count . ' soru)</span>';
                    echo '</li>';
                }
                echo '</ul>';
                echo '</div>';

            } else {
                echo '<div class="alert alert-success">Tüm sorular doğru cevaplanmış!</div>';
            }

            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<div class="alert alert-info">Bu derste henüz sınav bulunmamaktadır.</div>';
    }

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Hata: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
}

echo '</div>';
echo $OUTPUT->footer();