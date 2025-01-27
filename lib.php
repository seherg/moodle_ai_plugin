<?php
defined('MOODLE_INTERNAL') || die();

function local_ai_yorumu_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;

    // Sadece kurs sayfasında göster
    if (!$PAGE->course || $PAGE->course->id == SITEID) {
        return;
    }

    // Eğer kullanıcı bu yetkiye sahipse düğmeyi ekle
    if (has_capability('local/ai_yorumu:view', $context)) {
        $url = new moodle_url('/local/ai_yorumu/index.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('ai_analysis', 'local_ai_yorumu'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/settings', get_string('ai_analysis', 'local_ai_yorumu'))
        );
    }
}