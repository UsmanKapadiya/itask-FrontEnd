<?php

namespace App\Console\Commands;

use App\Models\ProjectTaskDetail;
use App\Models\UserDetail;
use Illuminate\Console\Command;

class RepeatTaskProject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repeat:projecttask';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'It is used to repeat task/project';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $all_users = UserDetail::where("isVerified", 1)->whereIn("type", array(config("constants.device_type.ios"), config("constants.device_type.android")))->get();
            foreach (count($all_users) > 0 ? $all_users : array() as $u) {
                $current_time = ($u->timezone != "" ? $u->timezone : config("app.timezone"));
                $data = ProjectTaskDetail::join("member_details", "project_task_details.id", "=", "member_details.ptId")->selectRaw("project_task_details.*")->where("member_details.memberId", $u->id)->whereRaw('project_task_details.dueDate != ""')->whereRaw("(project_task_details.repeat != 'Never' and project_task_details.repeat != '')")->where("project_task_details.status", "!=", config("constants.project_status.completed"))->get();
                foreach (count($data) > 0 ? $data : array() as $d) {
                    repeatChildProjectTask($d, $d->repeat,0, $current_time);
                }
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
