<?php
require_once INST_PATH.'app/controllers/admin_base_trait.php';
class CommandController extends Page {
    use AdminBaseTrait;

    public $noTemplate = ['execcommands'];

    public $excepts_before_filter = [
        'actions' => 'execcommands'
    ];

    public function __construct() {
        $this->_init();
        $this->_model = 'command';
    }
    
    public function addeditAction() {
        $this->layout = false;
        if(!empty($this->params['id'])):
            $this->project = $this->Project->Find($this->params['id']);
            $this->data = $this->Command->Find_by_project_id($this->project->id);

            if($this->data->counter() < 1):
                $this->data = $this->Command->Niu();
            endif;
        endif;
    }

    public function savecommandsAction() {
        $code = HTTP_422;
        $response = [
            'd' => [],
            'message' => 'Error Saving'
        ];

        if(!empty($_POST['command'])):
            foreach($_POST['command'] as $command):
                $data = $this->Command->Niu($command);
                (
                    $data->Save()
                    and ($response['d'] = $data and ($response['message'] = 'Success') and $code = HTTP_200)
                )
                or ($response['message'] = (string)$data->_error);
            endforeach;
        endif;

        http_response_code($code);
        $this->respondToAJAX(json_encode($response));
    }

    public function runbuildAction() {
        $code = HTTP_406;
        $response = [
            'd' => [],
            'message' => 'Nothing to run'
        ];

        if(!empty($this->params['id'])):
            $code = HTTP_201;
            $response['message'] = 'Runing';
            exec('cd '.INST_PATH." && dumbo run command/execcommands projectid={$this->params['id']} > /dev/null 2>&1 & ");
        endif;

        http_response_code($code);
        $this->respondToAJAX(json_encode($response));
    }

    private function _dispatchChainedProjects($mainProject) {
        $projects = $this->Project->Find_by_run_after($mainProject);
        if($projects->counter()):
            chdir(INST_PATH);
            foreach($projects as $project):
                exec("dumbo run command/execcommands projectid={$project->id} > /dev/null 2>&1 & ");
            endforeach;
        endif;
        return true;
    }

    public function execcommandsAction() {
        $this->layout = false;
        if(!empty($this->params['projectid'])):
            
            $success = 0;
            $returnval = 0;
            $percentage = 0;
            $project = $this->Project->Find($this->params['projectid']);
            $commands = $this->Command->Find_by_project_id($project->id)->getArray();
            $total = sizeof($commands);
            $execution = $this->Execution->Niu();
            $execution->project_id = $project->id;

            $executionsdir = INST_PATH.'app/webroot/executions/';
            is_dir($executionsdir) or mkdir($executionsdir, 0775);
            $projectexecs = "{$executionsdir}project-{$project->id}/";
            is_dir($projectexecs) or mkdir($projectexecs, 0775);

            ob_start();

            echo date('H:i:s'), ": Starting Job\n";
            while(null != ($command = array_shift($commands))):
                $escaped = escapeshellcmd($command['command']);
                chdir($project->path);
                echo date('H:i:s'), ": Running command {$escaped}\n";
                passthru($escaped, $returnval);
                if($returnval !== 0):
                    break;
                endif;
                $success++;
            endwhile;
            echo date('H:i:s'), ": Finishing Job\n";
            $buf = ob_get_clean();
            $percentage = (integer) round(($success / $total) * 100);
            $execution->result = (integer) ($percentage === 100);
            $execution->percentage = $percentage;
            $execution->Save();
            file_put_contents("{$projectexecs}execution_{$execution->id}.log", $buf);
            $execution->result and $this->_dispatchChainedProjects($project->id);
        endif;
        $this->render = ['layout'=>false, 'text'=>'Done.'];
    }
}
?>