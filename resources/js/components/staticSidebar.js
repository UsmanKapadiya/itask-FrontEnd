import React, { useState, useEffect, useCallback } from 'react';
import AccountCircleIcon from '@mui/icons-material/AccountCircle';
import BarChartIcon from '@mui/icons-material/BarChart';
import CreditCardIcon from '@mui/icons-material/CreditCard';
import DashboardIcon from '@mui/icons-material/Dashboard';
import LogoutIcon from '@mui/icons-material/Logout';
import PersonIcon from '@mui/icons-material/Person';
import SettingsRoundedIcon from '@mui/icons-material/SettingsRounded';
import TableChartIcon from '@mui/icons-material/TableChart';
import { Link } from 'react-router-dom';
import './Sidebar.scss';
import inboxImage from "../../../public/images/icon_left_inbox.png";
import projectImage from "../../../public/images/icon_left_projects.png";
import flagImage from "../../../public/images/icon_left_flag.png";
import reviewImage from "../../../public/images/icon_left_review.png";
import tagImage from "../../../public/images/icon_left_tag.png";



import { deleteProjectTask } from "./FunctionCalls";
import Pusher from 'pusher-js';
import Echo from 'laravel-echo';
const Sidebar = (props) => {
    const [state, setState] = useState({
        searchKey: '',
        sendKey: '',
        isUpdateProjects: 1,
        notificationCount: 0,
        commentData: {},
        isUpdateList: 0,
        isUpdateNotificationList: 0,
        showCompleted: '',
        projectOpen: false,
        isSearch: 0,
        commentProjectId: 0,
        documentProjectId: 0,
        isCommentDialogOpen: 0,
        isAttachmentDialogOpen: 0,
    });

    const updateState = (key, value) => {
        setState((prevState) => ({ ...prevState, [key]: value }));
    };

    const notificationRedirection = useCallback((data) => {
        if (
            [
                "add_project",
                "add_task",
                "add_comment",
                "member_removed_by",
                "complete_project",
                "complete_uncomplete",
                "reminder",
            ].includes(data.type)
        ) {
            if (data.type === "complete_uncomplete") {
                updateState("showCompleted", 1);
            }
            if (parseInt(data.pt_id) !== 0) {
                props.updateSelection("project", data.pt_id);
            } else {
                props.updateSelection("inbox", "1");
            }
            if (data.type === "add_comment") {
                updateState("commentData", data.display_data);
            }
        }
        if (data.type === "member_removed") {
            message.error(data.slient_msg);
        }
    }, [props]);

    const getNotificationCount = useCallback(() => {
        fetch('/notification-count')
            .then((res) => res.json())
            .then(
                (result) => {
                    updateState("notificationCount", result);
                },
                (error) => {
                    console.error(error);
                }
            );
    }, []);

    const refreshListing = useCallback((pt_id, n_type, result) => {
        if (parseInt(pt_id) !== 0) {
            if (props.section === "project" && props.value === pt_id) {
                updateState("isUpdateList", 1);
            } else if (props.section === "inbox" || props.section === "project") {
                if (result.all_ids !== undefined) {
                    let child_ids = result.all_ids.split(",");
                    for (let c in child_ids) {
                        if (parseInt(child_ids[c]) === parseInt(props.value)) {
                            updateState("isUpdateList", 1);
                        } else if (parseInt(child_ids[c]) === 0) {
                            updateState("isUpdateList", 1);
                            props.updateInboxCount();
                        }
                    }
                }
            }
            if (
                ["review", "flag", "search", "list_by_tag"].includes(props.section)
            ) {
                updateState("isUpdateList", 1);
            }
        } else {
            if (props.section === "inbox") {
                updateState("isUpdateList", 1);
            } else {
                props.updateInboxCount();
            }
        }
        if (
            [
                "add_project",
                "add_task",
                "complete_project",
                "complete_uncomplete",
                "removed_task",
                "refresh_list",
                "delete_project_task",
            ].includes(n_type)
        ) {
            updateState("isUpdateProjects", 1);
        }
    }, [props]);

    const deleteProjectTaskHandler = (id, type, popoverRef) => {
        popoverRef.current.props.onPopupVisibleChange(false);
        confirmAlert({
            title: `Delete ${type}`,
            message: "Are you sure want to delete?",
            buttons: [
                {
                    label: "Yes",
                    onClick: () => {
                        const data = { projectTaskId: id };
                        deleteProjectTask(data).then((res) => {
                            if (res.errorStatus) {
                                message.error(res.data);
                            } else {
                                message.success(res.data, 3);
                            }
                        });
                    },
                },
                {
                    label: "No",
                },
            ],
        });
    };

    useEffect(() => {
        Pusher.logToConsole = false;
        // Echo.channel("user" + '1fcedc560b41371faf48' + "." + 'c81e728d9d4c2f636f067f89cc14862c').listen(
        //     "ProjectEvent",
        //     (data) => {
        //         console.log("data :", data);
        //         if (data.text !== "") {
        //             notification.open({
        //                 key: data.type,
        //                 message: "iTask",
        //                 description: data.text,
        //                 duration: 3,
        //                 className: "cursor_pointer",
        //                 onClick: () => {
        //                     notificationRedirection(data);
        //                 },
        //             });
        //             if (
        //                 [
        //                     "add_project",
        //                     "add_task",
        //                     "add_comment",
        //                     "member_removed_by",
        //                     "complete_project",
        //                     "complete_uncomplete",
        //                 ].includes(data.type)
        //             ) {
        //                 refreshListing(data.pt_id, data.type, data);
        //             }
        //             getNotificationCount();
        //             updateState("isUpdateNotificationList", 1);
        //         } else {
        //             if (
        //                 [
        //                     "removed_task",
        //                     "refresh_list",
        //                     "updated_member",
        //                     "member_invited",
        //                 ].includes(data.type)
        //             ) {
        //                 refreshListing(data.pt_id, data.type, data);
        //                 if (data.type === "removed_task") {
        //                     getNotificationCount();
        //                     updateState("isUpdateNotificationList", 1);
        //                 }
        //             }
        //         }
        //     }
        // );
        getNotificationCount();
    }, [notificationRedirection, refreshListing, getNotificationCount]);


    return (
        <div className="sidebar">
            <div className="logo">
                <Link to="/" style={{ textDecoration: 'none' }}>
                    <h6 className="text_none">AdminDashboard</h6>
                </Link>
            </div>

            <div className="links">
                <ul className='ps-0'>
                    {/* <p className="spann">Main</p> */}
                    <Link to="/" style={{ textDecoration: 'none' }}>
                        <li>
                            {/* <DashboardIcon className="icon" />  */}
                            <img src={inboxImage} alt="Inbox" className="mr-2" />
                            <div className={"ps-2"}>Inbox</div>
                            <span className={"total-count ms-2"}>39</span>
                        </li>
                    </Link>

                    {/* <p className="spann">lists</p> */}
                    <Link to="/users" style={{ textDecoration: 'none' }}>
                        <li>
                            <img src={projectImage} alt="Inbox" className="mr-2" />
                            <div className={"ps-2"}>Projects</div>
                            {/* <PersonIcon className="icon" /> Projects */}
                        </li>
                    </Link>

                    <Link to="/products" style={{ textDecoration: 'none' }}>
                        <li>
                            <img src={flagImage} alt="Flagged" className={"mr-2 ml-3"} />
                            <div className={"ps-2"}>Flagged</div>
                            {/* <TableChartIcon className="icon" /> Flagged */}
                        </li>
                    </Link>
                    <Link to="/orders" style={{ textDecoration: 'none' }}>
                        <li>
                            <img src={reviewImage} alt="Review" className={"mr-2"} />
                            <div className='ps-2'>Review</div>
                            {/* <CreditCardIcon className="icon" /> Review */}
                        </li>
                    </Link>
                    <li>
                        <img src={tagImage} alt="Tags" className={"mr-2 ml-3"} />
                        <div className='ps-2'>Tags</div>
                        {/* <CreditCardIcon className="icon" /> Tags */}
                    </li>
                    <li>
                        <BarChartIcon className="icon" /> Completed Projects
                    </li>

                  
                </ul>
            </div>
        </div>
    );
}

export default Sidebar;
