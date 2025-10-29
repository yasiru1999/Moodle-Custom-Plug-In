<?php
// File: blocks/mylearninghours/block_mylearninghours.php

class block_mylearninghours extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_mylearninghours');
    }

    public function get_content() {
        global $PAGE, $USER, $DB;

        // Load custom CSS
        $PAGE->requires->css(new moodle_url('/blocks/mylearninghours/styles.css'));

        if ($this->content !== null) {
            return $this->content;
        }
        if (!isloggedin() || isguestuser()) {
            $this->content = new stdClass();
            $this->content->text = '';
            return $this->content;
        }

        $userid       = $USER->id;
        $totalhours   = $this->calculate_learning_hours($userid);
        $course_hours = $this->calculate_course_hours( $userid);
        $reward       = $this->get_reward_info($totalhours);

        // Build HTML
        $output  = html_writer::start_div('learning-hours-block');
        $output .= html_writer::tag(
            'p',
            get_string('totalhours','block_mylearninghours',$this->format_hours_minutes($totalhours)),
            ['class'=>'total-hours']
        );

        // Reward section
        $output .= html_writer::start_div('reward-info');
        $output .= html_writer::tag('span',$reward['icon'], ['class'=>'reward-icon']);
        $output .= html_writer::tag('span',' '.$reward['name'], ['class'=>'reward-name']);
        $output .= html_writer::tag('p',$reward['message'], ['class'=>'reward-message']);
        $output .= html_writer::end_div();

        // Course‑wise breakdown
        $output .= html_writer::start_div('course-hours-breakdown');

        $output .= html_writer::start_tag('ul',['class'=>'course-hours-list']);
        foreach ($course_hours as $cid => $hrs) {
            $course = $DB->get_record('course',['id'=>$cid],'shortname');
            $name   = ($course && $course->shortname)
                    ? $course->shortname
                    : "Course {$cid}";
            $label  = "{$name} – " . $this->format_hours_minutes($hrs);
            $output .= html_writer::tag('li',$label,['class'=>'course-hours-item']);
        }
        $output .= html_writer::end_tag('ul');
        $output .= html_writer::end_div();

        $output .= html_writer::end_div();

        $this->content = new stdClass();
        $this->content->text = $output;
        return $this->content;
    }

    // Total hours across all courses
    private function calculate_learning_hours($userid) {
        global $DB;
    
        // Get excluded course IDs from DB
        $excluded = $this->get_excluded_courseids();
        list($notin, $params) = $DB->get_in_or_equal($excluded, SQL_PARAMS_NAMED, 'ex', false);
        $params['u'] = $userid;
    
        $sql = "SELECT timecreated, courseid
                  FROM {logstore_standard_log}
                 WHERE userid = :u
                   AND courseid > 1
                   AND courseid $notin
              ORDER BY timecreated ASC";
    
        $logs = $DB->get_records_sql($sql, $params);
    
        $totsec = 0;
        $prev   = 0;
        foreach ($logs as $l) {
            // Check if course shortname starts with "Course "
            $course = $DB->get_record('course', ['id' => $l->courseid], 'shortname');
            if (!$course || stripos($course->shortname, 'Course ') === 0) {
                continue; // Skip
            }
    
            if ($prev) {
                $d = $l->timecreated - $prev;
                if ($d > 0 && $d < 1800) {
                    $totsec += $d;
                }
            }
            $prev = $l->timecreated;
        }
    
        return round($totsec / 3600, 2);
    }    
    

    // Per‑course hours
    private function calculate_course_hours($userid) {
        global $DB;
        $coursehours = [];
    
        // Get all excluded course IDs from DB filtering
        $excluded = $this->get_excluded_courseids();
        list($notin, $params) = $DB->get_in_or_equal($excluded, SQL_PARAMS_NAMED, 'ex', false);
        $params['u'] = $userid;
    
        // Get distinct course IDs from logs, excluding the known DB-excluded ones
        $cids = $DB->get_fieldset_sql(
            "SELECT DISTINCT courseid
               FROM {logstore_standard_log}
              WHERE userid = :u
                AND courseid > 1
                AND courseid $notin",
            $params
        );
    
        // Filter out "Course " prefixed shortnames in PHP
        foreach ($cids as $cid) {
            $course = $DB->get_record('course', ['id' => $cid], 'shortname');
            if (!$course || stripos($course->shortname, 'Course ') === 0) {
                continue; // skip course if shortname starts with "Course "
            }
    
            // Calculate hours
            $logs = $DB->get_records_sql(
                "SELECT timecreated
                   FROM {logstore_standard_log}
                  WHERE userid   = :u
                    AND courseid = :c
               ORDER BY timecreated ASC",
                ['u'=>$userid, 'c'=>$cid]
            );
            $prev = 0;
            $tot  = 0;
            foreach ($logs as $l) {
                if ($prev) {
                    $d = $l->timecreated - $prev;
                    if ($d > 0 && $d < 1800) {
                        $tot += $d;
                    }
                }
                $prev = $l->timecreated;
            }
            $coursehours[$cid] = round($tot/3600, 2);
        }
    
        return $coursehours;
    }    
    
    private function get_excluded_courseids() {
        global $DB;
    
        $excluded = [];
    
        // Courses starting with "Course "
        $startpattern = $DB->sql_like('shortname', ':prefix', false);
        $sql1 = "SELECT id FROM {course} WHERE $startpattern";
        $excluded1 = $DB->get_fieldset_sql($sql1, ['prefix' => 'Course %']);
    
        // "Request a Course"
        $excluded2 = $DB->get_fieldset_sql("SELECT id FROM {course} WHERE shortname = :short", ['short' => 'Request a Course']);
    
        // Courses in category with ID number 'udemy001'
        $catid = $DB->get_field('course_categories', 'id', ['idnumber' => 'udemy001']);
        $excluded3 = [];
        if ($catid) {
            $excluded3 = $DB->get_fieldset_select('course', 'id', 'category = :catid', ['catid' => $catid]);
        }

        // Exclude "Getting Started" course by shortname or fullname
        $excluded4 = $DB->get_fieldset_sql("SELECT id FROM {course} WHERE shortname = :short", ['short' => 'Getting Started']);
    
        // Merge all
        $excluded = array_merge($excluded1, $excluded2, $excluded3, $excluded4);
        return array_unique($excluded);
    }

    private function format_hours_minutes($hours) {
        $totalMinutes = (int) round($hours * 60);
        $h = floor($totalMinutes / 60);
        $m = $totalMinutes % 60;
    
        $result = '';
        if ($h > 0) {
            $result .= $h . ' hour' . ($h > 1 ? 's' : '');
        }
        if ($m > 0 || $result === '') {
            if ($result !== '') {
                $result .= ' ';
            }
            $result .= $m . ' min' . ($m > 1 ? 's' : '');
        }
        return $result;
    }
    
    

    // Reward levels
    private function get_reward_info($h) {
        if ($h < 10)   { return ['icon'=>'🔰','name'=>'Novice','message'=>'Every journey begins with a single step!']; }
        if ($h < 25)   { return ['icon'=>'🏅','name'=>'Explorer','message'=>'Starting your journey! Keep going!']; }
        if ($h < 50)   { return ['icon'=>'🎖️','name'=>'Learner','message'=>'You\'re building momentum!']; }
        if ($h < 100)  { return ['icon'=>'🏆','name'=>'Achiever','message'=>'Great progress! Keep pushing!']; }
        if ($h < 250)  { return ['icon'=>'🥇','name'=>'Proficient','message'=>'Strong foundation! You\'re excelling!']; }
        if ($h < 500)  { return ['icon'=>'🥈','name'=>'Expert','message'=>'Mastering skills! Almost a pro!']; }
        if ($h < 1000) { return ['icon'=>'🌟','name'=>'Master','message'=>'Exceptional dedication! True expertise!']; }
        if ($h < 5000) { return ['icon'=>'🔥','name'=>'Grandmaster','message'=>'Elite level! You\'ve reached mastery!']; }
                       return ['icon'=>'🏅','name'=>'Legend','message'=>'Ultimate dedication! A lifelong learner!'];
    }
}

