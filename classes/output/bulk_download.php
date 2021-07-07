<?php
/**
 *
 * @package   mod_customcert
 * @copyright 2021 Fabrizio Manunta<fabrizio.manunta@sosor.eu>
 */

namespace mod_customcert\output;

defined('MOODLE_INTERNAL') || die();

class bulk_download {

    protected function _getIssuesList() {
    }
    
    public static function process($customCertId, $courseModule, $groupMode, $template) {
        
        $tempdir = sys_get_temp_dir() . '/customcert';
        mkdir($tempdir);
        
        // get total number of issues to define number of cycles
        $numberOfIssues = \mod_customcert\certificate::get_number_of_issues($customCertId, $courseModule, $groupMode);
        $limit = 50;
        // retrieves customcert issues in batches to avoid heavy queries
        $cycles = $numberOfIssues / $limit;

        for ($i = 0; $i <= $cycles; $i++) {

            $issuesList = \mod_customcert\certificate::get_issues($customCertId, $groupMode, $courseModule, $limit * $i, $limit, '');

            foreach ($issuesList as $userId => $certIssue) {
                $template = new \mod_customcert\template($template);
                $filename = sprintf(
                    $tempdir . '/%s_%s_%s.pdf', 
                    $certIssue->firstname, 
                    $certIssue->lastname, 
                    isset($certIssue->idnumber) ? $certIssue->idnumber : ''
                );
                file_put_contents(
                    $filename,
                    $template->generate_pdf(false, $userId, true),
                    FILE_APPEND
                );
            }
        }
        
        // zip files
        $zipfilename = sprintf('certificati_%s.zip', str_replace(' ', '_', $courseModule->name));
        $zipfilepath = "$tempdir/$zipfilename";
        exec("cd $tempdir && zip -r9 $zipfilename *.pdf");
        
        header('Content-Type: application/zip');
        if (headers_sent()) {
            throw new Exception('Some data has already been output to browser, can\'t send zip file');
        }
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('Content-Disposition: inline; filename=' . $zipfilename);
        header('Content-Length', filesize($zipfilepath));
        echo file_get_contents($zipfilepath);
        
        //cleanup
        exec("rm -fR $tempdir");
        
        exit();
    }
}

