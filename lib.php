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
 * Controller
 *
 * @package    block_reporttiles
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * This function get the reporttiles files
 * @param stdClass $course course object
 * @param stdClass $cm block instance record
 * @param context $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function block_reporttiles_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {
    global $CFG, $USER;
    if ($context->get_course_context(false)) {
        require_course_login($course);
    } else if ($CFG->forcelogin) {
        require_login();
    } else {
        // Get parent context and see if user have proper permission.
        $parentcontext = $context->get_parent_context();
        if ($parentcontext->contextlevel === CONTEXT_COURSECAT) {
            // Check if category is visible and user can view this category.
            if (!core_course_category::get($parentcontext->instanceid, IGNORE_MISSING)) {
                send_file_not_found();
            }
        } else if ($parentcontext->contextlevel === CONTEXT_USER && $parentcontext->instanceid != $USER->id) {
            // The block is in the context of a user, it is only visible to the user who it belongs to.
            send_file_not_found();
        }
        // At this point there is no way to check SYSTEM context, so ignoring it.
    }
    if ($filearea == 'reporttiles') {
        $itemid = (int) array_shift($args);
        $fs = get_file_storage();
        $filename = array_pop($args);
        if (empty($args)) {
            $filepath = '/';
        } else {
            $filepath = '/' . implode('/', $args) . '/';
        }

        $file = $fs->get_file($context->id, 'block_reporttiles', $filearea, $itemid, $filepath, $filename);

        if (!$file) {
            return false;
        }
        if ($parentcontext = context::instance_by_id($cm->parentcontextid, IGNORE_MISSING)) {
            if ($parentcontext->contextlevel == CONTEXT_USER) {
                $forcedownload = true;
            }
        } else {
            $forcedownload = true;
        }
        \core\session\manager::write_close();
        send_stored_file($file, null, 0, $forcedownload, $options);
    }
    send_file_not_found();
}
/**
 * Parses CSS before it is cached.
 *
 * This function can make alterations and replace patterns within the CSS.
 *
 * @param string $css The CSS
 * @param theme_config $theme The theme config object.
 * @return string The parsed CSS The parsed CSS.
 */
function block_reporttiles_process_css($css, $theme) {

    // Set custom CSS.
    $customcss = '';
    $css = block_reporttiles_set_customcss($css, $customcss);

    // Define the default settings for the theme incase they've not been set.
    $defaults = [
        '[[setting:bordercolor]]' => '#009688',
    ];

    // Get all the defined settings for the theme and replace defaults.
    foreach ($theme->settings as $key => $val) {
        if (array_key_exists('[[setting:'.$key.']]', $defaults) && !empty($val)) {
            $defaults['[[setting:'.$key.']]'] = $val;
        }
    }

    // Replace the CSS with values from the $defaults array.
    $css = strtr($css, $defaults);
    return $css;
}

/**
 * Adds any custom CSS to the CSS before it is cached.
 *
 * @param string $css The original CSS.
 * @param string $customcss The custom CSS to add.
 * @return string The CSS which now contains our custom CSS.
 */
function block_reporttiles_set_customcss($css, $customcss) {
    $tag = '[[setting:customcss]]';
    $replacement = $customcss;
    if (is_null($replacement)) {
        $replacement = '';
    }

    $css = str_replace($tag, $replacement, $css);

    return $css;
}
/**
 * This function displays icon for reporttiles
 * @param  int $itemid Reporttiles icon item id
 * @param  int $blockinstanceid Reporttiles block instance id
 * @param  int $reportname Report name to display the respective icon
 * @return string Reprttiles logo
 */
function block_reporttiles_get_icon($itemid, $blockinstanceid, $reportname) {
    global $DB, $CFG, $OUTPUT;
    $reportname = str_replace(' ', '', $reportname);
    $filesql = "SELECT * FROM {files} WHERE itemid = :itemid AND component = :component
                AND filearea = :filearea AND filesize <> :filesize";
    $file = $DB->get_record_sql($filesql, ['itemid' => $itemid,
        'component' => 'block_reporttiles', 'filearea' => 'reporttiles', 'filesize' => 0, ]);
    if (empty($file)) {
        $defaultlogoexists = $CFG->dirroot . '/blocks/reporttiles/pix/' . $reportname.'.png';
        if (file_exists($defaultlogoexists)) {
            $defaultlogo = $OUTPUT->image_url($reportname, 'block_reporttiles');
        } else {
            $defaultlogo = $OUTPUT->image_url('sample_reporttile', 'block_reporttiles');
        }
        $logo = $defaultlogo;
    } else {
        $context = context_block::instance($blockinstanceid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'block_reporttiles', 'reporttiles', $file->itemid, 'filename', false);
        $url = [];
        if (!empty($files)) {
            foreach ($files as $file) {
                $isimage = $file->is_valid_image();
                $filename = $file->get_filename();
                $ctxid = $file->get_contextid();
                $itemid = $file->get_itemid();
                if ($isimage) {
                    $url[] = moodle_url::make_pluginfile_url($ctxid, 'block_reporttiles',
                        'reporttiles', $itemid, '/', $filename)->out(false);
                }
            }
            if (!empty($url[0])) {
                $logo = $url[0];
            } else {
                $defaultlogo = $OUTPUT->image_url('sample_reporttile', 'block_reporttiles');
                $logo = $defaultlogo;
            }
        } else {
            return $OUTPUT->image_url('sample_reporttile', 'block_reporttiles');
        }
    }
    return  $logo;
}
