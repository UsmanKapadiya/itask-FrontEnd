import React, { useState, useEffect, useRef, useContext } from 'react';
import { Button, Modal, ModalHeader, ModalBody } from 'reactstrap';
import Dropzone from 'react-dropzone';
import Datetime from 'react-datetime';
import { Dropdown, Menu, message, Spin, Alert } from 'antd';

import { addTask } from "./FunctionCalls";
import addImage from '../../../public/images/icon_nav_add.png';
import DisplayTag from "./DisplayTag";
import ProjectContext from "./projectContext";
import * as options from "./Constants";

const AddTaskModal = (props) => {
    const [state, setState] = useState({
        modal: false,
        taskname: '',
        date: '',
        repeat: 'Never',
        frequency: '',
        frequency_count: '',
        displayDueDate: false,
        displayRepeat: '',
        reminder: 'None',
        priority: '4',
        parentproject: '0',
        parentProjectLabel: '',
        memberSuggestion: [],
        type: 'task',
        status: '1',
        tags: [],
        members: [],
        comment: '',
        files: [],
        errors: {},
        isDisplaySpinner: false,
        isDisplayBtnSpinner: false,
        tagsData: {
            tag: [],
            member: [],
            reminder: [],
            attachment: [],
            project: [],
            comment: []
        },
        isReceiveData: 0
    });

    const commentElement = useRef(null);
    const parentProjectElement = useRef(null);
    const context = useContext(ProjectContext);

    useEffect(() => {
        document.addEventListener("mousedown", handleOutsideClick);
        return () => {
            document.removeEventListener("mousedown", handleOutsideClick);
        };
    }, []);

    const toggle = () => {
        setState((prevState) => ({
            ...prevState,
            modal: !prevState.modal
        }));
    };

    const handleAdd = () => {
        setState((prevState) => ({
            ...prevState,
            isDisplayBtnSpinner: true
        }));

        const task = {
            taskname: state.taskname,
            date: state.date,
            repeat: displayRepeat(),
            type: state.type,
            status: state.status,
            reminder: state.reminder,
            priority: state.priority,
            tags: state.tags,
            parentproject: state.parentproject,
            members: state.members,
            comment: state.comment,
            files: state.files,
            errors: {}
        };

        addTask(task).then((res) => {
            if (res.errorStatus) {
                setState((prevState) => ({
                    ...prevState,
                    errors: res.data,
                    isDisplayBtnSpinner: false
                }));
            } else {
                setState((prevState) => ({
                    ...prevState,
                    modal: !prevState.modal,
                    isDisplayBtnSpinner: false
                }));
                message.success(res.data, 3);
            }
        });
    };

    const displayRepeat = () => {
        let repeat = state.repeat;

        if (state.repeat === "") {
            let count = state.frequency_count !== "" ? state.frequency_count : "1";
            if (state.frequency === "daily" || state.frequency === "")
                repeat = `Every ${count} ${count > 1 ? "Days" : "Day"}`;
            else if (state.frequency === "weekly")
                repeat = `Every ${count} ${count > 1 ? "Weeks" : "Week"}`;
            else if (state.frequency === "monthly")
                repeat = `Every ${count} ${count > 1 ? "Months" : "Month"}`;
            else if (state.frequency === "yearly")
                repeat = `Every ${count} ${count > 1 ? "Years" : "Year"}`;
        }

        setState((prevState) => ({
            ...prevState,
            displayRepeat: repeat
        }));

        return repeat;
    };

    const handleOutsideClick = (e) => {
        const classList = Array.from(e.target.classList);
        if (!classList.includes("rdt") && !e.target.classList.contains("ant-dropdown-link")) {
            setState((prevState) => ({
                ...prevState,
                displayDueDate: false
            }));
        }
    };

    const onDrop = (files) => {
        setState((prevState) => ({
            ...prevState,
            files: files
        }));
    };

    return (
        <div>
            {props.isHeader === 1 ? (
                <a onClick={toggle}>
                    <img src={addImage} alt="Add Task" />
                </a>
            ) : (
                <div
                    className="cursor_pointer mb-1"
                    onClick={() => {
                        props.popRef.current.props.onPopupVisibleChange(false);
                        toggle();
                    }}
                >
                    Add task
                </div>
            )}
            <Modal
                isOpen={state.modal}
                toggle={toggle}
                size="lg"
                onOpened={() => displayRepeat()}
                onClosed={() => setState((prevState) => ({ ...prevState, taskname: '', date: '', tags: [] }))}
            >
                <ModalHeader toggle={toggle}>Add Task</ModalHeader>
                <ModalBody>
                    {state.errors.not_found && <Alert color="danger">{state.errors.not_found}</Alert>}
                    <Spin spinning={state.isDisplaySpinner} size="large">
                        <div className="d-flex" id="task_modal">
                            <div className="task-tags-input w-75">
                                <input
                                    type="text"
                                    className="form-control border-0 p-0"
                                    onChange={(e) => setState({ ...state, taskname: e.target.value })}
                                    placeholder="Name"
                                    name="taskname"
                                />
                                <ul id="tags">
                                    {Object.keys(state.tagsData).map((name) =>
                                        state.tagsData[name].length > 0 &&
                                        state.tagsData[name].map((n, index) => (
                                            <DisplayTag key={`${n.type}_${index}`} data={n} type={n.type} />
                                        ))
                                    )}
                                </ul>
                            </div>
                            <input
                                type="text"
                                className="form-control w-25 dueDate"
                                name="dueDate"
                                placeholder="Schedule"
                                value={state.date}
                                onClick={() =>
                                    setState((prevState) => ({
                                        ...prevState,
                                        displayDueDate: !prevState.displayDueDate
                                    }))
                                }
                                autoComplete="off"
                            />
                        </div>
                        {state.displayDueDate && (
                            <div className="due-date-container">
                                <Datetime
                                    closeOnSelect
                                    isValidDate={(currentDate) => currentDate.isAfter(new Date())}
                                    onChange={(date) =>
                                        setState((prevState) => ({
                                            ...prevState,
                                            date: date.format("YYYY-MM-DD HH:mm")
                                        }))
                                    }
                                    open
                                    input={false}
                                    value={state.date ? new Date(state.date) : new Date()}
                                />
                            </div>
                        )}
                        <Button
                            color="primary"
                            onClick={handleAdd}
                            disabled={state.isDisplayBtnSpinner}
                        >
                            Add Task <Spin size="small" spinning={state.isDisplayBtnSpinner} />
                        </Button>
                    </Spin>
                </ModalBody>
            </Modal>
        </div>
    );
};

export default AddTaskModal;