import axios from 'axios'
import $ from 'jquery';

export const register = newUser => {
    return axios
        .post('/user-register', newUser, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            if (err.response.status == 422) {
                return {errorStatus: true, data: err.response.data.errors}
            }
        })
}

export const verifyCode = code => {
    return axios
        .post(
            '/verify-code',
            {
                code: code.vc1 + code.vc2 + code.vc3 + code.vc4,
                email: code.email,
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
            },
        )

        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const resendCode = email => {
    return axios
        .post(
            '/resend-code',
            {
                email: email.email,
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
            },
        )

        .then(response => {
            return {errorStatus: false, data: response.data.response}
        })
        .catch(err => {
            console.log(err)
        })
}

export const login = user => {
    return axios
        .post(
            'http://backend-itask.intelligrp.com/user-login',
            {
                email: user.email,
                password: user.password,
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
            },
        )
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const addProject = project => {
    const tags = JSON.stringify(project.tags)
    const members = JSON.stringify(project.members)
    const data = new FormData()
    data.append('name', project.projectname)
    data.append('date', project.date)
    data.append('repeat', project.repeat)
    data.append('reminder', project.reminder)
    data.append('flag', project.flag)
    data.append('color', project.projectcolor)
    data.append('parentproject', project.parentproject)
    data.append('members', members)
    data.append('type', project.type)
    data.append('status', project.status)
    data.append('tags', tags)
    data.append('note', project.note)
    let existing_id = [];
    project.files.forEach(file => {
        if (file.id != undefined) {
            existing_id.push(file.id)
        } else {
            data.append('files[]', file);
        }
    });
    if (project.isEdit) {
        data.append('attachment_ids', existing_id)
        data.append('project_id', project.projectId)
        data.append('isUpdateStatus', project.isUpdateStatus)
    }
    const url = project.isEdit ? "/update-project" : "/add-project";
    return axios
        .post(url, data, {
            headers: {
                'Content-Type': 'application/json',
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const addTask = task => {
    const tags = JSON.stringify(task.tags)
    const members = JSON.stringify(task.members)
    const data = new FormData()
    data.append('name', task.taskname)
    data.append('date', task.date)
    data.append('repeat', task.repeat)
    data.append('type', task.type)
    data.append('parentproject', task.parentproject)
    data.append('reminder', task.reminder)
    data.append('flag', task.priority)
    data.append('status', task.status)
    data.append('tags', tags)
    data.append('members', members)
    data.append('comment', task.comment)
    task.files.forEach(file => {
        data.append('files[]', file);
    });
    return axios
        .post('/add-task', data, {
            headers: {
                'Content-Type': 'application/json',
            },
        })

        .then(response => {
            // console.log("response :",response);
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

// Method to edit Task

export const editTask = task => {
    const tags = JSON.stringify(task.tags)
    const members = JSON.stringify(task.members)
    const data = new FormData()
    data.append('projectId', task.projectId)
    data.append('name', task.name)
    data.append('date', task.date)
    data.append('repeat', task.repeat)
    data.append('flag', task.priority)
    data.append('reminder', task.reminder)
    data.append('parentproject', task.parentproject)
    data.append('tags', tags)
    data.append('members', members)
    return axios
        .post('/save-update-task', data, {
            headers: {
                'Content-Type': 'application/json',
            },
        })

        .then(response => {
            // console.log("response :",response);
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const addTag = tag => {
    return axios
        .post(
            '/add-tag',
            {
                name: tag.tagname,
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        )

        .then(response => {
            // console.log("response :",response);
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const editTag = tag => {
    return axios
        .post(
            '/edit-tag',
            {
                id: tag.tagid,
                name: tag.tagname,
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        )

        .then(response => {
            // console.log("response :",response);
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const deleteTag = individualTag => {
    return axios
        .post(
            '/delete-tag',
            {
                tag_id: individualTag.tag_id,
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        )

        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const addComment = comment => {
    const data = new FormData()
    data.append('projectId', comment.projectId)
    data.append('comment', comment.comment)
    data.append('commentParentId', comment.parentId)
    if (comment.file.length > 0) {
        data.append('file', comment.file[0])
    } else {
        data.append('file', '')
    }

    return axios
        .post('/add-comment', data, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })

        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const saveComment = individualComment => {
    return axios
        .post(
            '/save-update-comment',
            {
                commentId: individualComment.commentId,
                comment: individualComment.comment
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        )

        .then(response => {
            // console.log("response :",response);
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const deleteComment = individualComment => {
    return axios
        .post(
            '/delete-comment',
            {
                commentId: individualComment.commentId,
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        )

        .then(response => {
            // console.log("response :",response);
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

// Method to invite member from assign members modal

export const inviteMember = inviteMembers => {
    const members = JSON.stringify(inviteMembers.members)
    const data = new FormData()
    data.append('projectId', inviteMembers.projectId)
    data.append('projectName', inviteMembers.projectName)
    data.append('members', members)
    return axios
        .post('/invite-member', data, {
            headers: {
                'Content-Type': 'application/json',
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}
// Method to remove member from assign members

export const removeMember = individualMember => {
    const data = new FormData()
    data.append('members', individualMember.memberId)
    data.append('projectId', individualMember.projectId)
    return axios
        .post(
            '/remove-member', data,
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        ).then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

// Method to delete attachment in attachment modal

export const deleteAttachment = individualAttachment => {
    return axios
        .post(
            '/delete-attachment',
            {
                documentId: individualAttachment.attachmentId,
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        )

        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

// Method to delete attachment in attachment modal

export const deleteMultipleAttachment = data => {
    return axios
        .post(
            '/delete-multiple-attachment',
            {
                documentIds: data.attachmentIds,
                projectId:data.projectId
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        )

        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

// Method to update attachments in attachment modal

export const updateAttachment = Attachments => {
    const data = new FormData()
    data.append('projectId', Attachments.projectId)
    Attachments.files.forEach(file => {
        data.append('files[]', file);
    });
    return axios
        .post(
            '/update-attachment', data,
            {
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        )

        .then(response => {
            // console.log("response :",response);
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const deleteProjectTask = data => {
    return axios
        .post('/delete-project-task', data, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}
export const completeUncompleteTask = data => {
    return axios
        .post('/complete-uncomplete-task', data, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const assignMemberTask = data => {
    return axios
        .post('/assign-member-task', data, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

// Method to update user

export const updateuser = User => {
    return axios
        .post('/user-update', User, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}
// Method to update timezone

export const updatetimezone = Timezone => {
    return axios
        .post('/update-timezone', Timezone, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

// Method to update via notification
export const updateReminderNotification = ReminderNotification => {
    return axios
        .post('/update-via-notification', ReminderNotification, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}
// Method to update Auto Reminder
export const updateautoreminder = Autoreminder => {
    return axios
        .post('/update-auto-reminder', Autoreminder, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

// Method to update Notification Setting
export const updateNotificationSetting = NotificationSetting => {
    return axios
        .post('/update-notification-setting', NotificationSetting, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}
export const completeProject = data => {
    return axios
        .post('/complete-project', data, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}

export const Readnotification = Notification => {
    return axios
        .post('/read-notification', Notification, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}
export const deleteAccount = () => {
    return axios
        .post('/delete-user', {}, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}
export const reorderData = data => {
    return axios
        .post('/move-task-project', data, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .then(response => {
            if (response.data.error_msg != '') {
                return {errorStatus: true, data: response.data.error_msg}
            } else {
                return {errorStatus: false, data: response.data.response}
            }
        })
        .catch(err => {
            console.log(err)
        })
}
