<?php
/**
 * Managment of task in background.
 *
 * @author  Javier Serrano <javier@latuteca.com>
 * @version  1.0
 * @package Uroboros
 * @subpackage Controllers
 */
class BackgroundController extends Page {
    public $layout = false;
    public $noTemplate = [
                'index',
                'copylibs',
                'gettext',
                'compilesass',
                'setfactories'
            ];

    public function indexAction() {
        /* NOOP */
    }

    private function _readFiles($path, $pattern) {
        $files = [];
        $dir = opendir($path);
        //first level, not subdirectories
        while(false !== ($file = readdir($dir))):
            $file !== '.' and $file !== '..' and is_file("{$path}{$file}") and preg_match($pattern, $file, $matches) === 1 and ($files[] = "{$path}{$file}");
        endwhile;
        closedir($dir);
        //Second level, subdirectories
        $dir = opendir($path);
        while(false !== ($file = readdir($dir))):
            if ($file !== '.' and $file !== '..' and is_dir("{$path}{$file}")):
                $dir1 = opendir("{$path}{$file}");
                while(false !== ($file1 = readdir($dir1))):
                    is_file("{$path}{$file}/{$file1}") and preg_match($pattern, $file1, $matches) === 1 and ($files[] = "{$path}{$file}/{$file1}");
                endwhile;
                closedir($dir1);
            endif;
        endwhile;
        closedir($dir);
        sort($files);

        return $files;
    }

    public function setsassAction() {
        $files = $this->_readFiles(INST_PATH.'ui-sources/components/', '/(.+)\.scss/');
        array_unshift($files, INST_PATH.'ui-sources/styles.scss');

        $this->render = ['text' => implode(PHP_EOL, $files)];
    }

    public function setdirectivesAction() {
        $files = $this->_readFiles(INST_PATH.'ui-sources/components/', '/^(?=.*\.directive)(?!.*?\.spec).+\.js$/');

        $this->render = ['text' => implode(PHP_EOL, $files)];
    }

    public function setfactoriesAction() {
        $files = $this->_readFiles(INST_PATH.'ui-sources/components/', '/^(?=.*\.factory)(?!.*?\.spec).+\.js$/');

        $this->render = ['text' => implode(PHP_EOL, $files)];
    }

    public function copylibsAction() {
        file_exists('app/webroot/libs/') or mkdir('app/webroot/libs/', 0775);
        $filesjs = $this->_readFiles(INST_PATH.'ui-sources/libs/', '/(.+)\.js/');
        $filescss = $this->_readFiles(INST_PATH.'ui-sources/libs/', '/(.+)\.css/');

        if(sizeof($filesjs) > 0):
            while (null !== ($file = array_shift($filesjs))):
                $name = basename($file);
                echo "copying: {$name}", PHP_EOL;
                copy($file, INST_PATH."app/webroot/libs/{$name}");
            endwhile;
        endif;

        if(sizeof($filescss) > 0):
            while (null !== ($file = array_shift($filescss))):
                $name = basename($file);
                echo "copying: {$name}", PHP_EOL;
                copy($file, INST_PATH."app/webroot/libs/{$name}");
            endwhile;
        endif;
    }
}

?>