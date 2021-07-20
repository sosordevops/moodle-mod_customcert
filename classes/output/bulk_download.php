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
        if (is_dir($tempdir)) {
            exec("rm -fR $tempdir");
        }
        $dirCreated = mkdir($tempdir);
        
        if (false === $dirCreated) {
            throw new \Exception('Cannot create temp dir');
        }
        
        // get total number of issues to define number of cycles
        $numberOfIssues = \mod_customcert\certificate::get_number_of_issues($customCertId, $courseModule, $groupMode);
        $limit = 50;
        // retrieves customcert issues in batches to avoid heavy queries
        $cycles = $numberOfIssues / $limit;

        $numerOfSavedCertificates = 0;
        for ($i = 0; $i <= $cycles; $i++) {

            $issuesList = \mod_customcert\certificate::get_issues($customCertId, $groupMode, $courseModule, $limit * $i, $limit, '');

            foreach ($issuesList as $userId => $certIssue) {
                $template = new \mod_customcert\template($template);
                $user = \core_user::get_user($userId);
                $filename = sprintf(
                    $tempdir . '/%s_%s_%s.pdf', 
                    $certIssue->firstname, 
                    $certIssue->lastname, 
                    isset($user->idnumber) ? $user->idnumber : $userId
                );
                $saved = file_put_contents(
                    $filename,
                    $template->generate_pdf(false, $userId, true),
                    FILE_APPEND
                );
                
                if (false !== $saved) {
                    $numerOfSavedCertificates++;
                }
            }
        }
        
        if($numerOfSavedCertificates > 0) {
            // zip files
            $zipfilename = strtolower(
                sprintf('certificati_%s.zip', str_replace(' ', '_', preg_replace("/[^A-Za-z0-9 ]/", '', $courseModule->name)))
            );
            $zipfilepath = "$tempdir/$zipfilename";
            exec("cd $tempdir && zip -r9 $zipfilename *.pdf");
            
            if (false === is_file($zipfilepath)) {
                throw new \Exception('File not created');
            }
            
	    header("Pragma: public");
	    header("Expires: 0");
	    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	    header("Cache-Control: public");
	    header("Content-Description: File Transfer");
	    header("Content-type: application/octet-stream");
	    header("Content-Disposition: attachment; filename=\"".$zipfilename."\"");
	    header("Content-Transfer-Encoding: binary");
	    header("Content-Length: ".filesize($zipfilepath));
	    ob_end_flush();
	    $read = readfile($zipfilepath);
            if (false === $read) {
                throw new \Excepion('Impossible to read zip archive');
            }
            exit;
        }

        
        //cleanup
        exec("rm -fR $tempdir");
        
        return;
    }
}

