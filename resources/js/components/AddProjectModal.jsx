import React, { useState, useEffect, useRef } from 'react';
import {
    Button,
    Modal,
    ModalHeader,
    ModalBody,
    ModalFooter,
    Alert,
} from 'reactstrap';
import AssignMembers from './AssignToProject';
import plusIcon from '../../../public/images/icon_left_add.png';
import { addProject } from './FunctionCalls';
import Taglist from "./AddTaskTagList";
import Flaglist from './AddTaskFlagList';
import Reminder from './AddTaskReminder';
import Datetime from 'react-datetime';
// import 'antd/dist/antd.css';
import * as options from './Constants';
import Dropzone from 'react-dropzone';
import { message, TreeSelect, Dropdown, Menu, Select, Spin } from 'antd';
import DisplayTag from "./DisplayTag";
import { confirmAlert } from "react-confirm-alert";

const AddProjectModal = (props) => {
    const [state, setState] = useState({
        error: null,
        projects: [],
        modal: false,
        flag: '4',
        projectname: '',
        date: '',
        repeat: 'Never',
        reminder: 'None',
        projectcolor: '000000',
        parentproject: '0',
        parent_project_status: "",
        memberSuggestion: [],
        members: [],
        type: 'project',
        status: 1,
        tags: [],
        showMe: false,
        frequency: '',
        frequency_count: '',
        files: [],
        errors: {},
        displayDueDate: false,
        displayRepeat: '',
        note: '',
        status_name: '',
        isDisplaySpinner: false,
        isDisplayBtnSpinner: false,
        tagsData: {
            'tag': [],
            'member': [],
            'reminder': [],
            'attachment': []
        }
    });

    const [isEdit, setIsEdit] = useState(props.isEdit || 0);
    const _isMounted = useRef(true);

    const { errors, isDisplaySpinner, projectname, date,tags, tagsData, memberSuggestion, members, displayDueDate, flag, reminder, projectcolor, parentproject, status, note } = state;
    // const { errors, isDisplaySpinner, projectname, date, , tagsData, memberSuggestion, members, displayDueDate, flag, reminder, projectcolor, parentproject, status, note } = state;
    useEffect(() => {
        _isMounted.current = true;
        document.addEventListener("mousedown", handleOutsideClick);

        if (props.projectId !== undefined) {
            setState((prevState) => ({
                ...prevState,
                modal: true,
                isDisplaySpinner: true,
            }));
            fetch('/project-detail-by-id', {
                method: 'post',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                body: JSON.stringify({ "projectId": props.projectId })
            })
                .then((res) => res.json())
                .then(
                    (result) => {
                        setState((prevState) => ({
                            ...prevState,
                            isDisplaySpinner: false,
                        }));
                        if (result.error_msg !== "") {
                            message.error(result.error_msg);
                            props.updateEditView();
                        } else {
                            const response_result = result.response;
                            setState((prevState) => ({
                                ...prevState,
                                ...response_result,
                            }));
                            createTag("tag");
                            createTag("member");
                            createTag("reminder");
                            createTag("attachment");
                            displayRepeat();
                        }
                    },
                    (error) => {
                        setState((prevState) => ({
                            ...prevState,
                            error,
                            isDisplaySpinner: false,
                        }));
                    }
                );
        }

        return () => {
            _isMounted.current = false;
            document.removeEventListener("mousedown", handleOutsideClick);
        };
    }, [props.projectId]);

    const handleOutsideClick = (e) => {
        const classList = Array.from(e.target.classList);
        if (!classList.includes("ant-dropdown-link") && !classList.includes("ant-dropdown-menu-item")) {
            setState((prevState) => ({
                ...prevState,
                displayDueDate: false,
            }));
        }
    };

    const toggle = (e) => {
        e.stopPropagation();
        setState((prevState) => ({
            ...prevState,
            modal: !prevState.modal,
        }));
    };

    const onChange = (e) => {
        const { name, value } = e.target;
        setState((prevState) => ({
            ...prevState,
            [name]: value,
        }));
    };

    const Add = () => {
        setState((prevState) => ({
            ...prevState,
            isDisplayBtnSpinner: true,
        }));

        const project = {
            projectname: state.projectname,
            date: state.date,
            repeat: displayRepeat(),
            reminder: state.reminder,
            flag: state.flag,
            projectcolor: state.projectcolor,
            parentproject: state.parentproject,
            members: state.members,
            type: state.type,
            status: state.status,
            tags: state.tags,
            files: state.files,
            errors: {},
            note: state.note,
            isEdit: isEdit,
            projectId: props.projectId || "",
        };

        addProject(project).then((res) => {
            if (res.errorStatus) {
                setState((prevState) => ({
                    ...prevState,
                    errors: res.data,
                    isDisplayBtnSpinner: false,
                }));
            } else {
                if (_isMounted.current) {
                    setState((prevState) => ({
                        ...prevState,
                        modal: false,
                        isDisplayBtnSpinner: false,
                    }));
                }
                message.success(res.data, 3);
            }
        });
    };

    const displayRepeat = () => {
        let repeat = state.repeat;
        if (state.repeat === "") {
            const count = state.frequency_count || "1";
            if (state.frequency === "daily" || state.frequency === "") {
                repeat = `Every ${count} ${count > 1 ? "Days" : "Day"}`;
            } else if (state.frequency === "weekly") {
                repeat = `Every ${count} ${count > 1 ? "Weeks" : "Week"}`;
            } else if (state.frequency === "monthly") {
                repeat = `Every ${count} ${count > 1 ? "Months" : "Month"}`;
            } else if (state.frequency === "yearly") {
                repeat = `Every ${count} ${count > 1 ? "Years" : "Year"}`;
            }
        }
        setState((prevState) => ({
            ...prevState,
            displayRepeat: repeat,
        }));
        return repeat;
    };

    const createTag = (type, state, setState, removeValue) => {
        if (type === "tag") {
            const tags = state.tags.map((tag, index) => ({
                type: 'tag',
                data: tag,
                removeFunction: () => removeValue('tag', index),
            }));
            setState((prevState) => ({
                ...prevState,
                tagsData: {
                    ...prevState.tagsData,
                    tag: tags,
                },
            }));
        } else if (type === "member") {
            const members = state.members.map((member, index) => ({
                type: 'member',
                data: member,
                removeFunction: () => removeValue('member', index),
            }));
            setState((prevState) => ({
                ...prevState,
                tagsData: {
                    ...prevState.tagsData,
                    member: members,
                },
            }));
        } else if (type === "reminder") {
            let reminder = [];
            if (state.reminder !== 'None') {
                reminder = [{
                    type: 'reminder',
                    data: state.reminder,
                    removeFunction: () => removeValue('reminder', state.reminder),
                }];
            }
            setState((prevState) => ({
                ...prevState,
                tagsData: {
                    ...prevState.tagsData,
                    reminder: reminder,
                },
            }));
        } else if (type === "attachment") {
            let files = [];
            if (Object.keys(state.files).length > 0) {
                files = Object.keys(state.files).map((file) => ({
                    type: 'attachment',
                    data: state.files[file].name,
                    link: state.files[file].url !== undefined ? state.files[file].url : undefined,
                    removeFunction: () => removeValue('attachment', file),
                }));
            }
            setState((prevState) => ({
                ...prevState,
                tagsData: {
                    ...prevState.tagsData,
                    attachment: files,
                },
            }));
        }
    };  
    const handleDateClick = () => {
        setState((prevState) => ({
            ...prevState,
            displayDueDate: !prevState.displayDueDate, // Toggle the visibility of the due date picker
        }));
    };
    const handleDrop = (acceptedFiles) => {
        const newFiles = acceptedFiles.map((file) => ({
            name: file.name,
            url: URL.createObjectURL(file), // Create a temporary URL for the file
        }));

        setState((prevState) => ({
            ...prevState,
            files: [...prevState.files, ...newFiles], // Append new files to the existing files
        }));
    };
    const setProjectcolor = (color) => {
        setState((prevState) => ({
            ...prevState,
            projectcolor: color, // Update the projectcolor in the state
        }));
    };
    
    const setParentproject = (value) => {
        setState((prevState) => ({
            ...prevState,
            parentproject: value, // Update the parentproject in the state
        }));
    };
    const setStatus = (value) => {
        setState((prevState) => ({
            ...prevState,
            status: value, // Update the status in the state
        }));
    };
    const handleAdd = () => {
        setState((prevState) => ({
            ...prevState,
            isDisplayBtnSpinner: true, // Show spinner while processing
        }));

        const project = {
            projectname: state.projectname,
            date: state.date,
            repeat: state.displayRepeat,
            reminder: state.reminder,
            flag: state.flag,
            projectcolor: state.projectcolor,
            parentproject: state.parentproject,
            members: state.members,
            type: state.type,
            status: state.status,
            tags: state.tags,
            files: state.files,
            note: state.note,
            isEdit: props.isEdit || 0,
            projectId: props.projectId || "",
        };

        addProject(project).then((res) => {
            if (res.errorStatus) {
                setState((prevState) => ({
                    ...prevState,
                    errors: res.data,
                    isDisplayBtnSpinner: false, // Hide spinner on error
                }));
            } else {
                setState((prevState) => ({
                    ...prevState,
                    modal: false,
                    isDisplayBtnSpinner: false, // Hide spinner on success
                }));
                message.success(res.data, 3); // Show success message
                if (props.onProjectAdded) {
                    props.onProjectAdded(); // Call callback if provided
                }
            }
        });
    };
    return (
        <>
        {!props.isEdit && props.parentId === undefined ? (
            <a onClick={toggle} style={{ cursor: 'pointer' }}>
                <img src={plusIcon} alt="add Project" />
            </a>
        ) : null}

        <Modal
            isOpen={state.modal}
            onOpened={() => {}}
            className="modal-lg AddProject"
            onClosed={() => {
                setModal(false);
            }}
        >
            <ModalHeader>{props.isEdit ? "Edit" : "Add"} project</ModalHeader>
            <ModalBody>
                {errors.not_found && <Alert color="danger">{errors.not_found}</Alert>}
                <Spin spinning={isDisplaySpinner} size="large">
                    <p className="font-weight-bold mb-0">Project name</p>
                    <div className="d-flex">
                        <div className="tags-input w-75">
                            <ul id="tags">
                                {Object.keys(tagsData).map((name) =>
                                    tagsData[name].length > 0 &&
                                    tagsData[name].map((n, index) => (
                                        <DisplayTag key={`${n.type}_${index}`} data={n} type={n.type} />
                                    ))
                                )}
                            </ul>
                            <input
                                type="text"
                                className="form-control add-project-name"
                                onChange={onChange}
                                name="projectname"
                                placeholder="Name"
                                value={projectname}
                            />
                        </div>
                        <input
                            type="text"
                            className="form-control w-25 dueDate"
                            name="dueDate"
                            placeholder="Schedule"
                            value={date}
                            onClick={handleDateClick}
                            autoComplete="off"
                        />
                        {memberSuggestion.length > 0 && (
                            <AssignMembers
                                memberSuggestion={memberSuggestion}
                                selectedMembers={members}
                            />
                        )}
                    </div>
                    {displayDueDate && (
                        <div className="due-date-container">
                            <Datetime
                                closeOnSelect
                                isValidDate={(currentDate) => currentDate.isAfter(new Date())}
                                onChange={handleDateChange}
                                open
                                input={false}
                                value={date ? new Date(date) : new Date()}
                            />
                            <div className="p-2 d-flex">
                                <Dropdown
                                    disabled={!date}
                                    overlay={
                                        <Menu onClick={handleRepeatChange}>
                                            <Menu.Item key="Never">Never</Menu.Item>
                                            <Menu.Item key="Every Day">Every Day</Menu.Item>
                                            <Menu.Item key="Every Week">Every Week</Menu.Item>
                                            <Menu.Item key="Every 2 Weeks">Every 2 Weeks</Menu.Item>
                                            <Menu.Item key="Every Month">Every Month</Menu.Item>
                                            <Menu.Divider />
                                            <Menu.ItemGroup title="Custom">
                                                <Menu.SubMenu title="Frequency" key="frequency">
                                                    <Menu.Item key="daily">Daily</Menu.Item>
                                                    <Menu.Item key="weekly">Weekly</Menu.Item>
                                                    <Menu.Item key="monthly">Monthly</Menu.Item>
                                                    <Menu.Item key="yearly">Yearly</Menu.Item>
                                                </Menu.SubMenu>
                                                <Menu.SubMenu title="Every" key="frequency_count">
                                                    <Menu.Item key="1">1</Menu.Item>
                                                    <Menu.Item key="2">2</Menu.Item>
                                                    <Menu.Item key="3">3</Menu.Item>
                                                    <Menu.Item key="4">4</Menu.Item>
                                                    <Menu.Item key="5">5</Menu.Item>
                                                    <Menu.Item key="6">6</Menu.Item>
                                                </Menu.SubMenu>
                                            </Menu.ItemGroup>
                                        </Menu>
                                    }
                                    trigger={['click']}
                                >
                                    <a className="ant-dropdown-link w-50" onClick={(e) => e.preventDefault()}>
                                        Repeat
                                    </a>
                                </Dropdown>
                                <div className="ml-2">{displayRepeat}</div>
                            </div>
                        </div>
                    )}
                    {errors.name && <label className="error">{errors.name}</label>}
                    <div className="d-flex justify-content-end my-3">
                        <Taglist selectedTags={tags} />
                        <Flaglist priority={flag} />
                        <Reminder selectedDueDate={date} reminder={reminder} />
                        <a className="cursor_pointer">
                            <img src={options.ATTACHMENT_IMAGE} alt="Attachment" />
                        </a>
                    </div>
                    <div className="position-relative">
                        {errors.files && <label className="error">{errors.files}</label>}
                        <Dropzone onDrop={handleDrop}>
                            {({ getRootProps, getInputProps }) => (
                                <div
                                    {...getRootProps({
                                        className: 'attachment bottom-22 cursor_pointer',
                                        'data-toggle': 'tooltip',
                                        title: 'Add attachment(s)',
                                    })}
                                >
                                    <input {...getInputProps()} />
                                </div>
                            )}
                        </Dropzone>
                    </div>
                    <div className="d-flex mb-3 justify-content-between">
                        <div className="add-project-col w-25">
                            <p className="font-weight-bold mb-0">Color</p>
                            <Select
                                style={{ width: '100%' }}
                                placeholder="Select project color"
                                defaultValue={projectcolor}
                                onChange={setProjectcolor}
                                optionLabelProp="label"
                                showSearch={false}
                            >
                                {options.colorOptions.map((color, index) => (
                                    <Select.Option key={index} value={color.value} label={color.label}>
                                        {color.label}
                                    </Select.Option>
                                ))}
                            </Select>
                        </div>
                        <div className="add-project-col" id="parent-project-select">
                            <p className="font-weight-bold mb-0">Parent project</p>
                            <TreeSelect
                                showSearch
                                style={{ width: '100%' }}
                                value={parentproject}
                                dropdownStyle={{ maxHeight: 400, overflow: 'auto' }}
                                placeholder="No parent"
                                allowClear
                                treeDefaultExpandAll
                                onChange={setParentproject}
                                treeData={props.projects}
                            />
                        </div>
                        <div className="add-project-col w-25">
                            <p className="font-weight-bold mb-0 mr-2">Status</p>
                            <Select
                                style={{ width: '100%' }}
                                placeholder="Select status"
                                value={status}
                                onChange={setStatus}
                                optionLabelProp="label"
                                showSearch={false}
                            >
                                {options.statusOptions.map((statusOption, index) => (
                                    <Select.Option
                                        key={index}
                                        disabled={!props.isEdit && (statusOption.name === "Complete" || statusOption.name === "Review")}
                                        value={statusOption.value}
                                        label={statusOption.label}
                                    >
                                        {statusOption.label}
                                    </Select.Option>
                                ))}
                            </Select>
                        </div>
                    </div>
                    <p className="font-weight-bold mb-0">Note</p>
                    <textarea
                        className="form-control"
                        placeholder="Note"
                        rows="2"
                        cols="3"
                        name="note"
                        onChange={onChange}
                        value={note}
                    />
                </Spin>
            </ModalBody>
            <ModalFooter>
                <Button color="secondary" onClick={toggle} disabled={state.isDisplayBtnSpinner}>
                    Cancel
                </Button>
                <Button color="primary" onClick={handleAdd} disabled={state.isDisplayBtnSpinner}>
                    {props.isEdit ? "Update" : "Add"} <Spin size="small" spinning={state.isDisplayBtnSpinner} />
                </Button>
            </ModalFooter>
        </Modal>
    </>
    );
};

export default AddProjectModal;