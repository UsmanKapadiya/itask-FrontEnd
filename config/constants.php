<?php

return [
    'project_status' => array(
        "active" => 1,
        "overdue" => 2,
        "completed" => 3,
        "review" => 4,
        "on_hold" => 5
    ),
    'type' => array("project" => "1", "task" => "2"),
    'invitation_status' => array(
        "pending" => "1",
        "accepted" => "2",
        "rejected" => "3"
    ),
    'device_type' => array("ios" => "1", "android" => "2"),
    'notification_setting' => array(
        "add_comment" => "Comments added",
        "task_assigned" => "Tasks assigned to me",
        "task_completed" => "Tasks completed",
        "task_uncompleted" => "Tasks incompleted",
        "add_member" => "Member joined project",
        "member_removed" => "Member removed from project",
        "project_completed" => "Project completed"
    )
];
