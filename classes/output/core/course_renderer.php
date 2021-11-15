<?php
// This file is part of
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.".
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License".
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   theme_ikbfu2021
 * @copyright 2021, Gleb Lobanov <mail@gleblobanov.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_ikbfu2021\output\core;

defined('MOODLE_INTERNAL') || die();

use \html_writer;
use \moodle_url;
 
class course_renderer extends \core_course_renderer {
    /**
     * Displays one course in the list of courses.
     *
     * This is an internal function, to display an information about just one course
     * please use {@link core_course_renderer::course_info_box()}
     *
     * @param coursecat_helper $chelper various display options
     * @param core_course_list_element|stdClass $course
     * @param string $additionalclasses additional classes to add to the main <div> tag (usually
     *    depend on the course position in list - first/last/even/odd)
     * @return string
     */
    protected function coursecat_coursebox(\coursecat_helper $chelper, $course, $additionalclasses = '') {
        if (!isset($this->strings->summary)) {
            $this->strings->summary = get_string('summary');
        }
        if ($chelper->get_show_courses() <= self::COURSECAT_SHOW_COURSES_COUNT) {
            return '';
        }
        if ($course instanceof \stdClass) {
            $course = new \core_course_list_element($course);
        }
        $content = '';
        $classes = trim('coursebox clearfix '. $additionalclasses);
        if ($chelper->get_show_courses() < self::COURSECAT_SHOW_COURSES_EXPANDED) {
            $classes .= ' collapsed';
        }

        $course_rating = self::get_course_rating($course->id);



        // .coursebox
        $content .= html_writer::start_tag('div', array(
            'class' => $classes,
            'data-courseid' => $course->id,
            'data-type' => self::COURSECAT_TYPE_COURSE,
        ));
        $coursename = $chelper->get_course_formatted_name($course);
        
        // Сheck if the length of the course name exceeds $count
        // If it exceeds limit the number of characters by $count and add $append
        $count = 70;
        $append = '...';
        if (mb_strlen($coursename) > $count) {
            $coursename = mb_substr($coursename,0,$count);
            $coursename .= $append;
        }
        
        $courselink = new moodle_url('/course/view.php', ['id' => $course->id]);
        
            $content .= html_writer::start_tag('a', array('href' => $courselink));

                $content .= html_writer::start_tag('div', array('class' => 'ikbfu2021-info'));
                    $content .= html_writer::start_tag('div', array('class' => 'ikbfu2021-row')); 
                        //$content .= $this->course_name($chelper, $course);
                        $content .= html_writer::tag('h3', $coursename, array('class' => 'coursename'));

                        $content .= html_writer::start_tag('div', ['class' => 'moreinfo']);
                        if ($chelper->get_show_courses() < self::COURSECAT_SHOW_COURSES_EXPANDED) {
                            if ($course->has_summary() || $course->has_course_contacts() || $course->has_course_overviewfiles()
                                || $course->has_custom_fields()) {
                                $url = new moodle_url('/course/info.php', ['id' => $course->id]);
                                $image = $this->output->pix_icon('i/info', $this->strings->summary);
                                $content .= html_writer::link($url, $image, ['title' => $this->strings->summary]);
                                // Make sure JS file to expand course content is included.
                                $this->coursecat_include_js();
                            }
                        }
                        $content .= html_writer::end_tag('div');

                        $content .= $this->course_overview_files($course);
                    $content .= html_writer::end_tag('div');
                    $content .= html_writer::start_tag('div', array('class' => 'ikbfu2021-row')); 
                        $content .= self::get_course_authors($course->id);
                    $content .= html_writer::end_tag('div');
                    
                    $content .= html_writer::start_tag('div', array('class' => 'ikbfu2021-row')); 
                    if ($course_rating != 0) {
                        $content .= html_writer::tag('span', '&#9733; ' . number_format($course_rating, 2), ['class' => 'ikbfu2021-course-card-footer']);
                    }
                    $content .= html_writer::end_tag('div');               
                    
                $content .= html_writer::end_tag('div');

            $content .= html_writer::end_tag('a');
        $content .= html_writer::end_tag('div'); // .coursebox
        return $content;
    }

    private static function get_course_rating(string $course_id) : float {
        global $DB;
        $ratings = $DB->get_records('block_rate_course', ['course' => $course_id], '', 'id, rating');

        $rating_count = count($ratings);

        if ($rating_count == 0) {
            return 0;
        }

        $rating_sum   = array_reduce($ratings, function($carry, $item) {return $carry + $item->rating;}, 0);
        $rating       = $rating_sum / $rating_count;

        return $rating;
    }

    private static function get_course_authors(string $course_id) : string {
        global $DB;
        $fields = $DB->get_records('customfield_field', ['shortname' => 'authors'], '', 'id');
        $field_id = current($fields)->id;
        $authors_records = $DB->get_records('customfield_data', ['instanceid' => $course_id, 'fieldid' => $field_id]);
        if (empty($authors_records)) {
            $default_authors = get_string('default_authors', 'theme_ikbfu2021');
            return $default_authors;
        } else {
            $authors = current($authors_records);
            return $authors->value;
        }
    }
    

    /**
     * Returns HTML to display a tree of subcategories and courses in the given category
     *
     * @param \coursecat_helper $chelper various display options
     * @param \core_course_category $coursecat top category (this category's name and description will NOT be added to the tree)
     * @return string
     */
    protected function coursecat_tree(\coursecat_helper $chelper, $coursecat) {
        // Reset the category expanded flag for this course category tree first.
        $this->categoryexpandedonload = false;
        $categorycontent = $this->coursecat_category_content($chelper, $coursecat, 0);
        if (empty($categorycontent)) {
            return '';
        }

        // Start content generation
        $content = '';
        $attributes = $chelper->get_and_erase_attributes('course_category_tree clearfix');
        $content .= html_writer::start_tag('div', $attributes);

        if ($coursecat->get_children_count()) {
            $classes = array(
                'collapseexpand', 'aabtn'
            );

            // Check if the category content contains subcategories with children's content loaded.
            if ($this->categoryexpandedonload) {
                $classes[] = 'collapse-all';
                $linkname = get_string('collapseall');
            } else {
                $linkname = get_string('expandall');
            }

            // Only show the collapse/expand if there are children to expand.
            $content .= html_writer::start_tag('div', array('class' => 'collapsible-actions'));
            $content .= html_writer::link('#', $linkname, array('class' => implode(' ', $classes)));
            $content .= html_writer::end_tag('div');
            $this->page->requires->strings_for_js(array('collapseall', 'expandall'), 'moodle');
        }

        $content .= html_writer::tag('div', $categorycontent, array('class' => 'content'));

        $content .= html_writer::end_tag('div'); // .course_category_tree

        return $content;
    }

        /**
     * Returns HTML to display course contacts.
     *
     * @param core_course_list_element $course
     * @return string
     */
    protected function course_contacts(\core_course_list_element $course) {
        $content = '';
        if ($course->has_course_contacts()) {
            $content .= html_writer::start_tag('ul', ['class' => 'ikbfu2021-teachers']);
            $count = 0;
            foreach ($course->get_course_contacts() as $coursecontact) {
                if ($count > 1) {
                    break;
                }
                $name =
                    html_writer::link(new \moodle_url('/user/view.php',
                        ['id' => $coursecontact['user']->id, 'course' => SITEID]),
                        $coursecontact['username']);
                $content .= html_writer::tag('li', $name);
                $count++;
            }
            $content .= html_writer::end_tag('ul');
        }
        return $content;
    }

    /**
     * Returns HTML to display the subcategories and courses in the given category
     *
     * This method is re-used by AJAX to expand content of not loaded category
     *
     * @param \coursecat_helper $chelper various display options
     * @param \core_course_category $coursecat
     * @param int $depth depth of the category in the current tree
     * @return string
     */
    protected function coursecat_category_content(\coursecat_helper $chelper, $coursecat, $depth) {
        $content = '';
        // Subcategories
        $content .= $this->coursecat_subcategories($chelper, $coursecat, $depth);

        // AUTO show courses: Courses will be shown expanded if this is not nested category,
        // and number of courses no bigger than $CFG->courseswithsummarieslimit.
        $showcoursesauto = $chelper->get_show_courses() == self::COURSECAT_SHOW_COURSES_AUTO;
        if ($showcoursesauto && $depth) {
            // this is definitely collapsed mode
            $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_COLLAPSED);
        }

        // Courses
        if ($chelper->get_show_courses() > \core_course_renderer::COURSECAT_SHOW_COURSES_COUNT) {
            $courses = array();
            if (!$chelper->get_courses_display_option('nodisplay')) {
                $courses = $coursecat->get_courses($chelper->get_courses_display_options());
            }
            if ($viewmoreurl = $chelper->get_courses_display_option('viewmoreurl')) {
                // the option for 'View more' link was specified, display more link (if it is link to category view page, add category id)
                if ($viewmoreurl->compare(new moodle_url('/course/index.php'), URL_MATCH_BASE)) {
                    $chelper->set_courses_display_option('viewmoreurl', new \moodle_url($viewmoreurl, array('categoryid' => $coursecat->id)));
                }
            }
            $content .= $this->coursecat_courses($chelper, $courses, $coursecat->get_courses_count());
            $pagination = $this->get_pagination($chelper, $courses, $coursecat->get_courses_count());
            $content .= html_writer::tag('div',$pagination,['class' => 'ikbfu-2021-pagination-row']);

        }

        if ($showcoursesauto) {
            // restore the show_courses back to AUTO
            $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_AUTO);
        }

        return $content;
    }
 
     /**
     * Renders the list of courses
     *
     * This is internal function, please use {@link core_course_renderer::courses_list()} or another public
     * method from outside of the class
     *
     * If list of courses is specified in $courses; the argument $chelper is only used
     * to retrieve display options and attributes, only methods get_show_courses(),
     * get_courses_display_option() and get_and_erase_attributes() are called.
     *
     * @param \coursecat_helper $chelper various display options
     * @param array $courses the list of courses to display
     * @param int|null $totalcount total number of courses (affects display mode if it is AUTO or pagination if applicable),
     *     defaulted to count($courses)
     * @return string
     */
    protected function coursecat_courses(\coursecat_helper $chelper, $courses, $totalcount = null) {
        global $CFG;
        if ($totalcount === null) {
            $totalcount = count($courses);
        }
        if (!$totalcount) {
            // Courses count is cached during courses retrieval.
            return '';
        }

        if ($chelper->get_show_courses() == self::COURSECAT_SHOW_COURSES_AUTO) {
            // In 'auto' course display mode we analyse if number of courses is more or less than $CFG->courseswithsummarieslimit
            if ($totalcount <= $CFG->courseswithsummarieslimit) {
                $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED);
            } else {
                $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_COLLAPSED);
            }
        }

        // display list of courses
        $attributes = $chelper->get_and_erase_attributes('courses');
        $content = html_writer::start_tag('div', $attributes);



        $coursecount = 0;
        foreach ($courses as $course) {
            $coursecount ++;
            $classes = ($coursecount%2) ? 'odd' : 'even';
            if ($coursecount == 1) {
                $classes .= ' first';
            }
            if ($coursecount >= count($courses)) {
                $classes .= ' last';
            }
            $content .= $this->coursecat_coursebox($chelper, $course, $classes);
        }

        
        if (!empty($morelink)) {
            $content .= $morelink;
        }

        $content .= html_writer::end_tag('div'); // .courses
        return $content;
    }


   
    protected function get_pagination(\coursecat_helper $chelper, $courses, $totalcount = null) {
        global $CFG;
        if ($totalcount === null) {
            $totalcount = count($courses);
        }
        if (!$totalcount) {
            // Courses count is cached during courses retrieval.
            return '';
        }
        // prepare content of paging bar if it is needed
        $paginationurl = $chelper->get_courses_display_option('paginationurl');
        $paginationallowall = $chelper->get_courses_display_option('paginationallowall');
        if ($totalcount > count($courses)) {
            // there are more results that can fit on one page
            if ($paginationurl) {
                // the option paginationurl was specified, display pagingbar
                $perpage = $chelper->get_courses_display_option('limit', $CFG->coursesperpage);
                $page = $chelper->get_courses_display_option('offset') / $perpage;
                $pagingbar = $this->paging_bar($totalcount, $page, $perpage,
                        $paginationurl->out(false, array('perpage' => $perpage)));
                if ($paginationallowall) {
                    $pagingbar .= html_writer::tag('div', html_writer::link($paginationurl->out(false, array('perpage' => 'all')),
                            get_string('showall', '', $totalcount)), array('class' => 'paging paging-showall'));
                }
            } else if ($viewmoreurl = $chelper->get_courses_display_option('viewmoreurl')) {
                // the option for 'View more' link was specified, display more link
                $viewmoretext = $chelper->get_courses_display_option('viewmoretext', new \lang_string('viewmore'));
                $morelink = html_writer::tag('div', html_writer::link($viewmoreurl, $viewmoretext),
                        array('class' => 'paging paging-morelink'));
            }
        } else if (($totalcount > $CFG->coursesperpage) && $paginationurl && $paginationallowall) {
            // there are more than one page of results and we are in 'view all' mode, suggest to go back to paginated view mode
            $pagingbar = html_writer::tag('div', html_writer::link($paginationurl->out(false, array('perpage' => $CFG->coursesperpage)),
                get_string('showperpage', '', $CFG->coursesperpage)), array('class' => 'paging paging-showperpage'));
        }
   
      
        return $pagingbar;
    }
}