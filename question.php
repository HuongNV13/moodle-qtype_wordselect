<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * wordselect question definition class.
 *
 * @package    qtype
 * @subpackage wordselect
 * @copyright  Marcus Green 2016)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Represents a wordselect question.
 *
 * @copyright  2016 Marcus Green

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_wordselect_question extends question_graded_automatically_with_countback {

    public $wrongresponsecount;
    public $rightresponsecount;
    public $markedselectables=false;
    public $questiontextsplit;

    
    /* the characters indicating a field to fill i.e. [cat] creates
     * a field where the correct answer is cat
     */
    public $delimitchars = "[]";

    /**
     * @param int $key stem number
     * @return string the question-type variable name.
     */
    public function field($place) {
        return 'p' . $place;
    }

    /**
     * The text with delimiters removed so the user cannot see
     * which words are the ones that should be selected. So The cow [jumped]
     * becomes The cow jumped
     */
    public function get_words() {
        $questiontextnodelim = $this->questiontext;
        $l = substr($this->delimitchars, 0, 1);
        $r = substr($this->delimitchars, 1, 1);
        $doubledelimstart = $l . $l;
        $doubledelimend = $r . $r;
        $allwords=array();
        
        /* strinp html tags otherwise there is all manner of clickable debris*/
        $this->selectable = explode(' ',strip_tags($questiontextnodelim));
        
        if (strpos($questiontextnodelim, $doubledelimstart) !== false) {
            $this->markedselectables=true;
            $fieldregex = '#\\' . $l . '+.*?\\' . $r . '+|[^ ]+#';
            //$questiontextnodelim = strip_tags($questiontextnodelim);
            $questiontextnodelim = $this->pad_angle_brackets($questiontextnodelim);
            $questiontextnodelim=strip_tags($questiontextnodelim);
            preg_match_all($fieldregex, trim($questiontextnodelim), $matches);
            $this->questiontextsplit=$matches[0];
            
            $matches= preg_replace('/\\' . $l . '/', '', $matches[0]);
            $matches= preg_replace('/\\' . $r . '/', '', $matches);
             
            $allwords=$matches;
        } else {
            $text = $this->pad_angle_brackets($this->questiontext);
            $questiontextnodelim = preg_replace('/\\' . $l . '/', '', $text);
            $questiontextnodelim = preg_replace('/\\' . $r . '/', '', $questiontextnodelim);
            $allwords = preg_split('@[\s+]@u', $questiontextnodelim);
        }
        return $allwords;
    }
    
    public function is_selectable($key,$value){
        if($this->markedselectables==false){
            $value=strip_tags($value);
            if($value >""){
            return true;
            }else{
                return false;
            }
        }
        $fragment = $this->questiontextsplit[$key];
        $l = substr($this->delimitchars, 0, 1);
        return $this->startsWith($l,$fragment);
    }
    
  function startsWith($needle, $haystack) {
     return preg_match('/^' . preg_quote($needle, '/') . '/', $haystack);
 }

    public function get_unselectable_words($questiontext) {
        $questiontext = $this->pad_angle_brackets($questiontext);
        $allwords = preg_split('/[\s\n]/', $questiontext);
        $unselectable = array();
        $started = false;
        foreach ($allwords as $key => $word) {
            $start = substr($word, 0, 1);
            $len = strlen($word);
            $end = substr($word, $len - 1, $len);
            if ($start == "*") {
                print $start;
                $started = true;
            }
            if ($end == "*") {
                $started = false;
                $unselectable[$key] = $word;
            }
            if ($started == true) {
                $unselectable[$key] = $word;
            }
        }
        return $unselectable;
    }

    public static function pad_angle_brackets($questiontext) {
        // Put a space before and after tags so they get split as words.
        $text = str_replace('>', '> ', $questiontext);
        $text = str_replace('<', ' <', $text);
        return $text;
    }

    /**
     * @param string $questiontext
     * @param string $delimitchars
     * @return array index places in array of correct words
     * Split the question text into words delimited by spaces
     * then return an array of all the words that are correct
     * i.e. surrounded by the delimit chars. Note that
     * word in this context means any string that can be separated
     * by a space marker so that will include html etc
     */
    public static function get_correct_places($questiontext, $delimitchars) {
        $correctplaces = array();
        $text = self::pad_angle_brackets($questiontext);
        $allwords = preg_split('/[\s\n]/', $text);
        $l = substr($delimitchars, 0, 1);
        $r = substr($delimitchars, 1, 1);
        foreach ($allwords as $key => $word) {
            $regex = '/\\' . $l . '.*\\' . $r . '/';
            if (preg_match($regex, $word)) {
                $correctplaces[] = $key;
            }
        }
        return $correctplaces;
    }

    /**
     * Return an array of the question type variables that could be submitted
     * as part of a question of this type, with their types, so they can be
     * properly cleaned.
     * @return array variable name => PARAM_... constant.
     */
    public function get_expected_data() {
        $wordcount = count($this->get_words());
        for ($key = 0; $key < $wordcount; $key++) {
            $data['p' . $key] = PARAM_RAW_TRIMMED;
        }
        return $data;
    }

    /**
     * @param array $response
     * @return string
     * summary of response shown in the responses report
     */
    public function summarise_response(array $response) {
        return "";//mavg
        $summary = '';
        $allwords = $this->get_words();
        foreach ($response as $index => $value) {
            $summary .= " " . $allwords[substr($index, 1)] . " ";
        }
        return $summary;
    }

    public function is_word_selected($place, $response) {
        $responseplace = 'p' . $place;
        if (isset($response[$responseplace]) && (($response[$responseplace] == "on" ) || ($response[$responseplace] == "true" ) )) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * @param array $response
     * @return boolean
     * If any words have been selected
     */
    public function is_complete_response(array $response) {
        if (count($response) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function get_validation_error(array $response) {
        // TODO.
        return '';
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        /* if you are moving from viewing one question to another this will
         * discard the processing if the answer has not changed. If you don't
         * use this method it will constantantly generate new question steps and
         * the question will be repeatedly set to incomplete. This is a comparison of
         * the equality of two arrays. Without this deferred feedback behaviour probably
         * wont work.
         */
        if ($prevresponse === $newresponse) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return question_answer an answer that
     * contains the a response that would get full marks.
     * used in preview mode. If this doesn't return a
     * correct value the button labeled "Fill in correct response"
     * in the preview form will not work. This value gets written
     * into the rightanswer field of the question_attempts table
     * when a quiz containing this question starts.
     */
    public function get_correct_response() {
        $correctplaces = $this->get_correct_places($this->questiontext, $this->delimitchars);
        $correctresponse = array();
        foreach ($correctplaces as $place) {
            $correctresponse['p' . $place] = 'on';
        }
        return $correctresponse;
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'answerfeedback') {
            $currentanswer = $qa->get_last_qt_var('answer');
            $answer = $this->get_matching_answer(array('answer' => $currentanswer));
            $answerid = reset($args); // Itemid is answer id.
            return $options->feedback && $answer && $answerid == $answer->id;
        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }

    public function is_correct_place($correctplaces, $place) {
        if (in_array($place, $correctplaces)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array $response responses, as returned by
     * {@link question_attempt_step::get_qt_data()}.
     * @return array (number, integer) the fraction, and the state.
     */
    public function grade_response(array $response) {
        $totalscore = 0;
        $correctplaces = $this->get_correct_places($this->questiontext, $this->delimitchars);
        $this->wrongresponsecount = $this->get_wrong_responsecount($correctplaces, $response);
        foreach ($correctplaces as $place) {
            if (isset($response['p' . $place])) {
                if (( $response['p' . $place] === 'on') || ( $response['p' . $place] === 'true')) {
                    $this->rightresponsecount++;
                }
            }
        }
        $wrongfraction = @($this->wrongresponsecount / count($correctplaces));
        $fraction = @($this->rightresponsecount / count($correctplaces));
        $fraction = max(0, $fraction - $wrongfraction);
        $grade = array($fraction, question_state::graded_state_for_fraction($fraction));
        return $grade;
    }

    /* not called in interactive mode */

    public function compute_final_grade($responses, $totaltries) {
        $totalscore = 0;
        $correctplaces = $this->get_correct_places($this->questiontext, $this->delimitchars);
        $wrongresponsecount = $this->get_wrong_responsecount($correctplaces, $responses[0]);
        foreach ($correctplaces as $place) {
            $lastwrongindex = -1;
            $finallyright = false;
            foreach ($responses as $i => $response) {
                if (!array_key_exists(('p' . $place), $response)) {
                    $lastwrongindex = $i;
                    $finallyright = false;
                    continue;
                } else {
                    $finallyright = true;
                }
            }
            if ($finallyright) {
                $totalscore += max(0, 1 - ($lastwrongindex + 1) * $this->penalty);
            }
        }
        $wrongfraction = @($wrongresponsecount / count($correctplaces));
        $totalscore = $totalscore / count($correctplaces);
        $totalscore = max(0, $totalscore - $wrongfraction);
        return $totalscore;
    }

    /**
     * Used when calculating the final grade
     * @param type $correctplaces
     * @param type $response
     * @return int
     */
    public function get_wrong_responsecount($correctplaces, $responses) {
        $wrongresponsecount = 0;
        foreach ($responses as $key => $value) {
            /* chop off the leading p */
            $place = substr($key, 1);
            /* if its not in the correct places and it is turned on */
            if (!in_array($place, $correctplaces) && ($value == 'on')) {
                $wrongresponsecount++;
            }
        }
        return $wrongresponsecount;
    }

}
