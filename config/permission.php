<?php

return [
    "permissions" => [
        "project" => [
            "view" => ["creator", "project_member"],
            "edit" => ["creator"],
            "delete" => ["creator"],
            "add_member" => ["creator"],
            "edit_member" => ["creator"],
            "add_sub_project" => ["creator"],
            "add_task" => ["creator"],
            "view_comments" => ["creator", "project_member"],
            "add_comment" => ["creator", "project_member"],
            "delete_comment" => ["creator"],
            "view_document" => ["creator", "project_member"],
            "add_document" => ["creator", "project_member"],
            "delete_document" => ["creator", "project_member"]
        ],
        "sub_project" => [
            "view" => ["creator", "project_member"],
            "edit" => ["creator"],
            "delete" => ["creator"],
            "add_member" => ["creator"],
            "edit_member" => ["creator"],
            "add_task" => ["creator"],
            "view_comments" => ["creator", "project_member"],
            "add_comment" => ["creator", "project_member"],
            "delete_comment" => ["creator"],
            "view_document" => ["creator", "project_member"],
            "add_document" => ["creator", "project_member"],
            "delete_document" => ["creator", "project_member"]
        ],
        "task" => [
            "view" => ["creator", "task_member"],
            "edit" => ["creator"],
            "delete" => ["creator"],
            "add_member" => ["creator"],
            "edit_member" => ["creator"],
            "view_comments" => ["creator", "task_member"],
            "add_comment" => ["creator", "task_member"],
            "delete_comment" => ["creator"],
            "view_document" => ["creator", "task_member"],
            "add_document" => ["creator", "task_member"],
            "delete_document" => ["creator", "task_member"],
        ]
    ]
];
