#!/usr/bin/php -d display_errors
<?php
namespace UIBuilder;

$dir = dirname(realpath(__FILE__));
defined('INST_PATH') || define('INST_PATH', exec('pwd').'/');
set_include_path(
    '/etc/dumbophp'.PATH_SEPARATOR.
    '/etc/dumbophp/bin'.PATH_SEPARATOR.
    '/etc/dumbophp/lib'.PATH_SEPARATOR.
    INST_PATH.'vendor'.PATH_SEPARATOR.
    INST_PATH.'vendor/rantes/dumbophp'.PATH_SEPARATOR.
    INST_PATH.'vendor/rantes/dumbophp/bin'.PATH_SEPARATOR.
    INST_PATH.'vendor/rantes/dumbophp/lib'.PATH_SEPARATOR.
    INST_PATH.PATH_SEPARATOR.
    get_include_path().PATH_SEPARATOR.
    PEAR_EXTENSION_DIR.PATH_SEPARATOR.
    '/windows/dumbophp'.PATH_SEPARATOR.
    '/windows/dumbophp/bin'.PATH_SEPARATOR.
    '/windows/dumbophp/lib'.PATH_SEPARATOR.
    '/windows/system32/dumbophp'.PATH_SEPARATOR.
    '/windows/system32/dumbophp/bin'.PATH_SEPARATOR.
    '/windows/system32/dumbophp/lib'.PATH_SEPARATOR.
    INST_PATH.'DumboPHP'
);

require_once 'dumbophp.php';

require_once '/etc/dumboChromeDriver/src/DevToolsException.php';
require_once '/etc/dumboChromeDriver/src/ChromeProcess.php';
require_once '/etc/dumboChromeDriver/src/DevToolsSession.php';
require_once '/etc/dumboChromeDriver/src/DevToolsClient.php';

spl_autoload_register(function ($class) {
    $path = explode('\\', $class);
    require_once implode('/', array_slice($path, 1)).'.php';

});


use DumboPHP\lib\DumboShellColors;
use function DumboPHP\strGenerate;
use function DumboPHP\Camelize;

class generatorException extends \Exception {}

class UIGenerator {
    private $_path = '';
    private $_dumboJsDirectiveImportHeader = '';
    private $_mainConfig = null;
    private $_dmbSRC = '';

    public function __construct($path) {
        $this->_mainConfig = json_decode(file_get_contents('./dumbojs.conf.json'));
        $this->_dmbSRC = $this->_mainConfig->dumbojsSource;
        $this->_path = $path;
        $this->_dumboJsDirectiveImportHeader =<<<DUMBO
import { DumboDirective } from '{$this->_dmbSRC}dumbo.min.js';
DUMBO;
    }

    public function component($name) {
        if (!preg_match('/^[a-z0-9-]+$/', $name)):
            throw new generatorException(
                "Nombre de componente inválido: '{$name}'. " .
                "Solo minúsculas, números y guiones " .
                "(ej: dmb-mi-componente)."
            );
        endif;

        $componentsPath = "{$this->_path}components/{$name}";
        $sourceJS = "{$name}.directive.js";
        $testJS = "{$name}.directive.spec.js";
        $sass = "{$name}.scss";
        $tpl = "{$name}.html";
        $camelizedName = Camelize($name, '-');

        if(!mkdir($componentsPath)):
            throw new generatorException("Cannot create component directory: {$componentsPath}");
        endif;

        $tplContent = <<<DUMBO
<p>This is the {$name} component</p>
DUMBO;

        $directiveContent = <<<DUMBO
{$this->_dumboJsDirectiveImportHeader}

export class {$camelizedName} extends DumboDirective {
    static selector = '{$name}';
    static templateUrl = '{$tpl}';

    constructor() {
        super();
    }

    init () {
    }
}

DUMBO;
        $specContent = <<<DUMBO
import { DumboTestApp } from '{$this->_dmbSRC}dumbo.min.js';
import { {$camelizedName} } from './{$sourceJS}';

describe('{$camelizedName} Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        {$camelizedName}
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture({$camelizedName});
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });
});
DUMBO;
        $sassContent = <<<DUMBO
{$name} {
}
DUMBO;
        file_put_contents("{$componentsPath}/{$sourceJS}", $directiveContent);
        file_put_contents("{$componentsPath}/{$testJS}", $specContent);
        file_put_contents("{$componentsPath}/{$sass}", $sassContent);
        file_put_contents("{$componentsPath}/{$tpl}", $tplContent);
    }
}
class UIBuilder {
    public $shellOutput = true;
    private $_colors = null;
    private $_command = null;
    private $_arguments = [];
    private $_params = [];
    private $_commands = [
        'build',
        'generate',
        'test'
    ];
    private $_options = [
        'watch' => ['value' => false, 'cast' => 'boolean']
    ];
    private $_specFiles = [];
    private $_mainConfig = null;
    private $_dmbSRC = '';
    private $uid = '';
    private $nonce = '';

    public function __construct() {

        $this->_colors = new DumboShellColors();
        $this->_mainConfig = json_decode(file_get_contents('./dumbojs.conf.json'));
        $this->_dmbSRC = $this->_mainConfig->src;
        $this->uid = $_SERVER['UNIQUE_ID'] ?? strGenerate();
        $this->nonce = 'nonce-'.base64_encode($this->uid);
    }

    private function _logger($source, $message) {
        if (empty($source) or empty($message) or !is_string($source) or !is_string($message)):
            return false;
        endif;
        $logdir = INST_PATH.'tmp/logs/';
        is_dir($logdir) or mkdir($logdir, 0777, true);
        $file = "{$source}.log";
        $stamp = date('d-m-Y i:s:H');

        file_exists("{$logdir}{$file}")
            and filesize("{$logdir}{$file}") >= 524288000
            and rename("{$logdir}{$file}", "{$logdir}{$stamp}_{$file}");
        $this->shellOutput and fwrite(STDOUT, "{$message}\n");
        file_put_contents("{$logdir}{$file}", "[{$stamp}] - {$message}\n", FILE_APPEND);
        return true;
    }

    private function _readFiles($path, $pattern, $goUnder = true) {
        $files = [];
        $dir = opendir($path);
        //first level, not subdirectories
        while(false !== ($file = readdir($dir))):
            $file !== '.'
                and $file !== '..'
                and is_file("{$path}{$file}")
                and preg_match($pattern, $file, $matches) === 1
                and ($files[] = "{$path}{$file}");
        endwhile;
        closedir($dir);
        //Second level, subdirectories
        if ($goUnder):
            $dir = opendir($path);
            while(false !== ($file = readdir($dir))):
                $npath = "{$path}{$file}";
                if ($file !== '.' and $file !== '..' and is_dir($npath) and is_readable($npath)):
                    $dir1 = opendir("{$path}{$file}");
                    if(false !== $dir1):
                        while(false !== ($file1 = readdir($dir1))):
                            is_file("{$path}{$file}/{$file1}")
                                and preg_match($pattern, $file1, $matches) === 1
                                and ($files[] = "{$path}{$file}/{$file1}");
                        endwhile;
                        closedir($dir1);
                    endif;
                endif;
            endwhile;
            closedir($dir);
        endif;
        sort($files);

        return $files;
    }

    private function _parseOptions() {
        $trueFalse = ['true' => true, 'false' => false];
        foreach($this->_arguments as $i => $arg) {
            preg_match('@\-\-([a-zA-Z0-9]+)\=([a-z0-9\-\_\/]+)[\s]*@im', $arg, $match);
            if (sizeof($match) === 3) {
                if(isset($this->_options[$match[1]])){
                    switch($this->_options[$match[1]]['cast']) {
                        case 'numeric':
                            $match[2] = (integer)$match[2];
                        break;
                        case 'boolean':
                            $match[2] = $trueFalse[strtolower($match[2])];
                        break;
                        case 'string':
                            $match[2] = trim((string)$match[2]);
                        break;
                        default:
                            throw new \Exception("Value not allowed for {$match[1]}");
                        break;
                    }
                    $this->_options[$match[1]]['value'] = strlen($match[2]) > 0 ? $match[2] : null;
                }
                $this->_arguments[$i] = null;
                unset($this->_arguments[$i]);
            }
        }
    }

    private function _cleanJS($code) {
        $pattern = '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/';
        $code = preg_replace($pattern, '', $code);
        $search = [
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
           '/(\s)+/s',         // shorten multiple whitespace sequences
            '/<!--(.|\s)*?-->/' // Remove HTML comments
        ];
        $replace = [
            '>',
            '<',
            '\\1',
            ''
        ];
        return preg_replace($search, $replace, $code);
    }

    private function _cleanHTML($code) {
        $search = [
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/<!--(.|\s)*?-->/' // Remove HTML comments
        ];
        $replace = [
            '>',
            '<',
            ''
        ];
        return preg_replace($search, $replace, $code);
    }

    public function sassAction() {
        is_dir(INST_PATH.'app/webroot/css') or mkdir(INST_PATH.'app/webroot/css');
        $sass = new \Sass();
        $sass->setStyle(\Sass::STYLE_COMPRESSED);
        $sass->setIncludePath(INST_PATH.'ui-components/base-sass');
        $files = $this->_readFiles(INST_PATH.'ui-components/components/', '/(.+)\.scss/');
        array_unshift($files, INST_PATH.'ui-components/styles.scss');
        $bigFile = '';
        if(sizeof($files) > 0):
            while (null !== ($file = array_shift($files))):
                $bigFile .= file_get_contents($file)."\n";
            endwhile;
        endif;
        $css = $sass->compile($bigFile);
        file_put_contents(INST_PATH.'app/webroot/css/styles.css', $css);
    }

    public function setspecsAction() {
        $files = $this->_readFiles(INST_PATH."{$this->_mainConfig->src}components/", '/^(?=.*\.spec).+\.js$/');
        $this->_specFiles = [];

        if(sizeof($files) > 0):
            while (null !== ($file = array_shift($files))):
                $this->_specFiles[] = str_replace(INST_PATH, '/', $file);
            endwhile;
        endif;
    }

    public function setlibsAction() {
        file_exists('app/webroot/ui-components/libs/') or mkdir('app/webroot/ui-components/libs/', 0775);
        $filesjs = $this->_readFiles(INST_PATH."{$this->_dmbSRC}/libs/", '/(.+)\.js/');
        $filescss = $this->_readFiles(INST_PATH."{$this->_dmbSRC}/libs/", '/(.+)\.css/');

        if(sizeof($filesjs) > 0):
            while (null !== ($file = array_shift($filesjs))):
                $name = basename($file);
                copy($file, INST_PATH."app/webroot/ui-components/libs/{$name}");
            endwhile;
        endif;

        if(sizeof($filescss) > 0):
            while (null !== ($file = array_shift($filescss))):
                $name = basename($file);
                copy($file, INST_PATH."app/webroot/ui-components/libs/{$name}");
            endwhile;
        endif;
    }

    public function setComponents() {
        $filesjs = $this->_readFiles(INST_PATH."{$this->_mainConfig->src}components/", '/(.+)\.js/');
        $fileContent = '';

        if(sizeof($filesjs) > 0):
            while (null !== ($file = array_shift($filesjs))):
                $name = basename($file);
                $fileContent = file_get_contents($file);
                $fileContent = $this->_cleanJS($fileContent);
                $fileContent = $this->_setImport($fileContent);

                file_put_contents(INST_PATH."dist/{$name}", $fileContent);
            endwhile;
        endif;
    }

    public function setTemplates() {
        $files = $this->_readFiles(INST_PATH."{$this->_mainConfig->src}components/", '/(.+)\.html$/');
        $fileContent = '';
        $tpls = '';

        if(sizeof($files) > 0):
            while (null !== ($file = array_shift($files))):
                $name = basename($file);
                $nameClean = ucfirst(str_replace('.html', '', $name));
                $fileContent = file_get_contents($file);
                $fileContent = $this->_cleanHTML($fileContent);
                $tpls .= "<template id=\"{$nameClean}-template\">{$fileContent}</template>\n";
            endwhile;
            file_put_contents(INST_PATH."app/views/_ui_components-templates.phtml", $tpls);
        endif;
    }

    // public function setIndexes() {
    //     $directives = $this->_readFiles(INST_PATH."{$this->_mainConfig->src}components/", '/^(?=.*\.directive).+\.js$/');
    //     $factories = $this->_readFiles(INST_PATH."{$this->_mainConfig->src}components/", '/^(?=.*\.factory).+\.js$/');

    //     $includes = [];
    //     if(sizeof($directives) > 0):
    //         while (null !== ($file = array_shift($directives))):
    //             $includes[] = "export * from './".basename($file)."';";
    //         endwhile;
    //     endif;

    //     file_put_contents(INST_PATH.'{$this->_mainConfig->src}components/directives.js', implode("\n",$includes));

    //     $includes = [];
    //     if(sizeof($factories) > 0):
    //         while (null !== ($file = array_shift($factories))):
    //             $includes[] = "export * from './".basename($file)."';";
    //         endwhile;
    //     endif;

    //     file_put_contents(INST_PATH."{$this->_mainConfig->src}components/factories.js", implode("\n",$includes));
    // }


    public function buildUIAction() {
        $this->sassAction();
        $this->setTemplates();
        $this->setTestPageAction();

        $this->_options['watch']['value'] && $this->watchUIAction();
    }

    public function setTestPageAction() {
        $this->_logger('dumbo_ui_builder', 'Building files...');
        $start = microtime(true);

        $this->setspecsAction();
        $specs = '';

        while(null !== ($file = array_shift($this->_specFiles))):
            $specs =<<<DUMBO
    {$specs}
    <script src="{$file}" type="module"></script>
DUMBO;
        endwhile;
        $templates = file_get_contents(INST_PATH."app/views/_ui_components-templates.phtml");
        $page = <<<DUMBO
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Tests">
    <meta name="keywords" content="test">
    <meta name="theme-color" content="#16253F">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Dumbo UI tests</title>
    <link rel="preconnect" href="./">

    <link rel="stylesheet" type="text/css" href="/ui-components/libs/styles/jasmine.css">
    <link rel="stylesheet" type="text/css" href="/ui-components/libs/styles/dmb-styles.css">

    <link rel="preload" as="style" type="text/css" href="/ui-components/libs/main.css">
    <link rel="preload" as="style" type="text/css" href="/ui-components/libs/styles/dmb-styles.css">
    <link rel="preload" as="style" type="text/css" href="/css/styles.css">

    <link rel="stylesheet" type="text/css" href="/ui-components/libs/main.css">
    <link rel="stylesheet" type="text/css" href="/ui-components/libs/styles/dmb-styles.css">
    <link rel="stylesheet" type="text/css" href="/css/styles.css">

</head>
<body>
    <div class="html-reporter">
        <div class="banner">
        </div>
        <ul class="symbol-summary"></ul>
        <div class="alert">
        </div>
        <div class="results">
        </div>
    </div>
    <div id="components">
    </div>

    {$templates}

    <script src="/libs/jasmine.js" type="text/javascript"></script>
    <script src="/libs/jasmine-html.js" type="text/javascript"></script>
    <script src="/libs/jasmine-boot.js" type="text/javascript"></script>
    {$specs}
</body>
</html>
DUMBO;

        file_put_contents(INST_PATH.'app/webroot/test.html',$page);
        $total = microtime(true) - $start;
        $this->_logger('dumbo_ui_builder', "Jobs finished, took {$total} seconds.");
    }

    public function testUIAction() {
        $this->buildUIAction();

        // Servidor PHP embebido — sin cambios
        $descriptorsserver = [
            ['pipe', 'r'], ['pipe', 'w'],
            ['file', sys_get_temp_dir() . '/error-output.txt', 'a'],
        ];
        $cwd = './app/webroot/';
        $processServer = proc_open(
            'php -S localhost:3456', $descriptorsserver,
            $pipeserver, $cwd, []
        );

        $client = new \DumboChromeDriver\DevToolsClient();

        try {
            $client->start('http://localhost:3456/test.html');

            // Da tiempo a que Jasmine termine de correr —
            // considera un mecanismo de polling en vez de
            // sleep fijo si el tiempo de ejecución varía mucho
            sleep(2);

            $result = $client->evaluate(<<<JS
            (() => {
                const results = document.querySelector(
                    '.jasmine_html-reporter'
                );
                const duration = results.querySelector(
                    '.jasmine-duration'
                );
                const overall = results.querySelector(
                    '.jasmine-overall-result'
                );
                return `\${duration.innerHTML} - \${overall.innerText}`;
            })()
            JS);

            $this->_logger('dumbo_ui_unit_testing', (string)$result);

            preg_match('@((?:\d)+)\sfailures@', (string)$result, $matches);
            if (!empty($matches)):
                fwrite(STDERR, "{$matches[0]}\n");
            endif;
        } catch (\DumboChromeDriver\DevToolsException $e) {
            $this->showError("Error ejecutando tests: " . $e->getMessage());
        } finally {
            $client->stop();
            if (is_resource($processServer)):
                fclose($pipeserver[0]);
                fclose($pipeserver[1]);
                proc_terminate($processServer);
                proc_close($processServer);
            endif;
            // proc_terminate() solo alcanza al proceso directo de
            // proc_open() (el shell "sh -c"); "php -S" corre como un
            // fork real por debajo y queda huérfano (confirmado
            // empíricamente, mismo caso que ChromeProcess::stop() en
            // DumboChromeDriver). Se complementa matando por firma.
            shell_exec("pkill -f 'php -S localhost:3456' 2>/dev/null");
        }
    }

    private function help() {
        $text = <<<DUMBO
▓█████▄  █    ██  ███▄ ▄███▓ ▄▄▄▄    ▒█████
▒██▀ ██▌ ██  ▓██▒▓██▒▀█▀ ██▒▓█████▄ ▒██▒  ██▒
░██   █▌▓██  ▒██░▓██    ▓██░▒██▒ ▄██▒██░  ██▒
░▓█▄   ▌▓▓█  ░██░▒██    ▒██ ▒██░█▀  ▒██   ██░
░▒████▓ ▒▒█████▓ ▒██▒   ░██▒░▓█  ▀█▓░ ████▓▒░
 ▒▒▓  ▒ ░▒▓▒ ▒ ▒ ░ ▒░   ░  ░░▒▓███▀▒░ ▒░▒░▒░
 ░ ▒  ▒ ░░▒░ ░ ░ ░  ░      ░▒░▒   ░   ░ ▒ ▒░
 ░ ░  ░  ░░░ ░ ░ ░      ░    ░    ░ ░ ░ ░ ▒
   ░       ░            ░    ░          ░ ░
 ░                                ░

DumboPHP 2.0 by Rantes
DumboUI shell.
Ussage:

    dumbo <command> <option> <params>

Commands:

    build
        Builds the UI components

    Options:
        --watch=[true|false]    Set a daemon to watch files (used in tests)
    
    generate [component] <name>
        Generates scripts for component.


DUMBO;
        fwrite(STDOUT, $text . "\n");
    }

    public function watchUIAction() {
        $this->_logger('dumbo_ui_watcher', 'Setting up files for watch...');
        $files = new \ArrayObject();
        $list = [
            ...$this->_readFiles(INST_PATH.'ui-components/actions/', '/^(.+)\.js$/'),
            ...$this->_readFiles(INST_PATH.'ui-components/base-sass/', '/(.+)\.scss/', false),
            ...$this->_readFiles(INST_PATH.'ui-components/components/', '/^(.+)\.js$/'),
            ...$this->_readFiles(INST_PATH.'ui-components/components/', '/^(.+)\.html$/'),
            ...$this->_readFiles(INST_PATH.'ui-components/components/', '/(.+)\.scss/'),
            ...$this->_readFiles(INST_PATH.'ui-components/libs/', '/(.+)\.js/'),
            ...$this->_readFiles(INST_PATH.'ui-components/libs/', '/(.+)\.css/'),
            ...$this->_readFiles(INST_PATH.'ui-components/', '/(.+)\.scss/', false)
        ];
        $this->_logger('dumbo_ui_watcher', "Watching for changes in files: \n".implode("\n", $list));

        foreach($list as $file):
            $stats = stat($file);
            $files[] = ['path'=> $file, 'mtime' => $stats['mtime']];
        endforeach;
        $this->_logger('dumbo_ui_watcher', 'Watching files...');
        while(true):
            foreach($files as  $index => $file):
                $stats = stat($file['path']);
                if($stats['size'] > 0 and $file['mtime'] !== $stats['mtime']):
                    $this->_logger('dumbo_ui_watcher', "File changed {$file['path']}");
                    $files[$index]['mtime'] = $stats['mtime'];
                    $this->_logger('dumbo_ui_watcher', 'Runing tasks...');
                    $start = microtime(true);
                    $this->sassAction();
                    $this->setTemplates();
                    $this->setTestPageAction();
                    $total = microtime(true) - $start;
                    $this->_logger('dumbo_ui_watcher', "Jobs finished, took {$total} seconds.");
                    break;
                endif;
            endforeach;
            usleep(500000);
        endwhile;
    }

    public function showError($errorMessage) {
        fwrite(STDOUT, $this->_colors->getColoredString($errorMessage, "white", "red") . "\n");
    }

    public function showMessage($errorMessage) {
        fwrite(STDOUT, $this->_colors->getColoredString($errorMessage, "white", "green") . "\n");
    }

    public function showNotice($errorMessage) {
        fwrite(STDOUT, $this->_colors->getColoredString($errorMessage, "blue", "yellow") . "\n");
    }

    private function generateScripts() {
        if(empty($this->_arguments[0]) && sizeof($this->_arguments) < 2) {
            $this->help();
            die('Error: Missing params.');
        }

        for ($i=1; $i < sizeof($this->_arguments); $i++) {
            $this->_params[] = $this->_arguments[$i];
        }

        $generator = new UIGenerator(INST_PATH.'ui-components/');

        switch ($this->_arguments[0]) {
            case 'component':
                $this->showNotice('Creating scaffold for "'.$this->_arguments[1].'".');
                $generator->component($this->_arguments[1]);
            break;

            default:
                $this->help();
                die('Option no valid for generate.');
            break;
        }
    }

    public function run($argv) {
        if(empty($argv[1])):
            $this->help();
            die('Error: Option not valid.');
        endif;

        array_shift($argv);
        $this->_command = array_shift($argv);
        $this->_arguments = $argv;
        $this->_parseOptions();

        if(in_array($this->_command, $this->_commands)):
            switch($this->_command):
                case 'generate':
                    $this->generateScripts();
                break;
                case 'test':
                    $this->testUIAction();
                break;
                case 'build':
                default:
                    $this->buildUIAction();
                break;
            endswitch;
        else:
            $this->help();
            die('Error: Option not valid.');
        endif;

    }
}
$builder = new UIBuilder();
$builder->run($argv);