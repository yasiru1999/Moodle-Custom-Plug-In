<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Block definition class for block_completion_stats.
 *
 * @package    block_completion_stats
 * @copyright  2025, Yasiru Navoda Jayasekara
 */

class block_completion_stats extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_completion_stats');
    }

    public function get_content() {
        global $OUTPUT, $COURSE, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // Get completion statistics
        $stats = $this->get_completion_stats($COURSE->id);

        // Calculate remaining (not completed both courses)
        $remaining = $stats['total'] - $stats['completed'];
        $remaining_percent = $stats['total'] > 0 ? round($remaining / $stats['total'] * 100, 1) : 0;

        // Prepare detailed breakdown data
        $breakdown = [];
        foreach ($stats['inprogress'] as $coursename => $count) {
            $percent = $stats['total'] > 0 ? round($count / $stats['total'] * 100, 1) : 0;
            $breakdown[] = [
                'label' => get_string('inprogress', 'block_completion_stats') . ' – ' . $coursename,
                'count' => $count,
                'percent' => $percent,
                'icon' => '🔄',
                'colorclass' => 'inprogress'
            ];
        }
        foreach ($stats['notstarted'] as $coursename => $count) {
            $percent = $stats['total'] > 0 ? round($count / $stats['total'] * 100, 1) : 0;
            $breakdown[] = [
                'label' => get_string('notstarted', 'block_completion_stats') . ' – ' . $coursename,
                'count' => $count,
                'percent' => $percent,
                'icon' => '⚠️',
                'colorclass' => 'notstarted'
            ];
        }
        
        // Prepare data for template
        $data = [
            'courseid' => $COURSE->id,
            'completed' => $stats['completed'],
            'remaining' => $remaining,
            'total' => $stats['total'],
            'completed_percent' => $stats['completed_percent'],
            'remaining_percent' => $remaining_percent,
            'chartid' => 'completion-chart-' . $this->instance->id,
            'breakdown' => $breakdown,
            'hasdata' => $stats['total'] > 0
        ];

        // Render template
        $this->content->text = $OUTPUT->render_from_template(
            'block_completion_stats/content',
            $data
        );

        // Add JavaScript
        $this->page->requires->js_call_amd(
            'block_completion_stats/chart',
            'init',
            [$data]
        );

        return $this->content;
    }

    private function get_completion_stats($courseid) {
        global $DB;

        // Courses we are tracking
        $courses = $DB->get_records_sql("SELECT id, fullname FROM {course} WHERE fullname IN ('Course1', 'Course2')");
        $courseids = array_keys($courses);

        // Get all active users
        $users = $DB->get_records('user', ['deleted' => 0, 'suspended' => 0]);

        $stats = [
            'total' => count($users),
            'completed' => 0,
            'inprogress' => [],
            'notstarted' => []
        ];

        // Initialize per-course counts
        foreach ($courses as $c) {
            $stats['inprogress'][$c->fullname] = 0;
            $stats['notstarted'][$c->fullname] = 0;
        }

        foreach ($users as $user) {
            $completed_courses = 0;

            foreach ($courses as $course) {
                $completion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);

                if ($completion && $completion->timecompleted) {
                    // User completed this course
                    $completed_courses++;
                } else {
                    // Check if user has started the course
                    if ($completion && $completion->timestarted) {
                        $stats['inprogress'][$course->fullname]++;
                    } else {
                        $stats['notstarted'][$course->fullname]++;
                    }
                }
            }

            // Count user as completed only if all courses are completed
            if ($completed_courses == count($courses)) {
                $stats['completed']++;
            }
        }

        // Completed percentage
        $stats['completed_percent'] = $stats['total'] > 0 ? round($stats['completed'] / $stats['total'] * 100, 1) : 0;

        return $stats;
    }


    public function applicable_formats() {
        return ['site-index' => true];
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function has_config() {
        return false;
    }
}
