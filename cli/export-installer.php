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
 * Exports the strings needed by the installer
 *
 * Moodle core contains a subset of strings needed for the start of the installation.
 * The list of required strings is maintained in install/stringnames.txt. This
 * script parses that file and exports the translations into a configured destination.
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

// list of branches to process
$branches = array(
    'MOODLE_27_STABLE',
    'MOODLE_28_STABLE',
    'MOODLE_29_STABLE',
    'MOODLE_30_STABLE',
    'MOODLE_31_STABLE',
);

fputs(STDOUT, "*****************************************\n");
fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " EXPORT INSTALLER JOB STARTED\n");

remove_dir(AMOS_EXPORT_INSTALLER_DIR, true);

foreach ($branches as $branch) {
    fputs(STDOUT, "BRANCH {$branch}\n");
    if ($branch == 'MOODLE_31_STABLE') {
        $gitbranch = 'origin/master';
    } else {
        $gitbranch = 'origin/' . $branch;
    }
    $version = mlang_version::by_branch($branch);

    // read the contents of stringnames.txt at the given branch
    chdir(AMOS_REPO_MOODLE);
    $gitout = array();
    $gitstatus = 0;
    $gitcmd = AMOS_PATH_GIT . " show {$gitbranch}:install/stringnames.txt";
    exec($gitcmd, $gitout, $gitstatus);

    if ($gitstatus <> 0) {
        fputs(STDERR, "ERROR EXECUTING {$gitcmd}\n");
        exit($gitstatus);
    }

    $list = array();    // [component][stringid] => true
    foreach ($gitout as $string) {
        list($stringid, $component) = array_map('trim', explode(',', $string));
        $list[$component][$stringid] = true;
    }
    unset($gitout);

    $tree = mlang_tools::components_tree(array('branch' => $version->code));
    $langs = array_keys($tree[$version->code]);
    unset($tree);

    $phpdoc = <<<EOF
/**
 * Automatically generated strings for Moodle installer
 *
 * Do not edit this file manually! It contains just a subset of strings
 * needed during the very first steps of installation. This file was
 * generated automatically by export-installer.php (which is part of AMOS
 * {@link http://docs.moodle.org/dev/Languages/AMOS}) using the
 * list of strings defined in /install/stringnames.txt.
 *
 * @package   installer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


EOF;

    $status = 0; // exit status, 0 means no problems

    foreach ($list as $componentname => $stringids) {
        foreach ($langs as $lang) {
            if ($lang === 'en_fix') {
                continue;
            }
            $component = mlang_component::from_snapshot($componentname, $lang, $version, null, false, false, array_keys($stringids));
            if ($component->has_string()) {
                $file = AMOS_EXPORT_INSTALLER_DIR . '/' . $version->dir . '/install/lang/' . $lang . '/' . $component->name . '.php';
                if (!file_exists(dirname($file))) {
                    mkdir(dirname($file), 0755, true);
                }
                $component->export_phpfile($file, $phpdoc);
            }
            if ($lang == 'en') {
                // check that all string were exported
                foreach (array_keys($stringids) as $stringid) {
                    if (!$component->has_string($stringid)) {
                        fputs(STDERR, "ERROR Unknown $stringid,$componentname\n");
                        $status = 1;
                    }
                }
            }
            $component->clear();
        }
    }
}

fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " EXPORT INSTALLER JOB DONE\n");

exit($status);
