import React, { useState, useEffect, useCallback } from "react";
// import Header from './Header';
import Sidebar from './Sidebar';
import Navbar from './Navbar';
import './Home.scss';

import UnassigntaskList from "./UnassigntaskList";
// import FlaggedAccordion from "./FlaggedAccordion";
// import FlagwiseList from "./FlagwiseList";
// import ReviewProjectList from "./ReviewProjectList";
// import reviewImage from "../../images/icon_left_review.png";
// import TagsAccordion from "./TagsAccordion";
// import ProjectAccordion from "./ProjectAccordion";
// import projectImage from "../../images/icon_left_projects.png";
// import ProjectdetailList from "./ProjectdetailList";
// import ProjectListAccordion from "./CompletedProjects";
// import AddProjectModal from "./AddProjectModal";
import SearchIcon from "../../../public/images/icon_nav_search.png";
// import Search from "./Search";
import Header from "./Header";
import { message, notification } from "antd";
import { ContextProvider } from "./projectContext";
// import Comment from "./Comment";
import { confirmAlert } from "react-confirm-alert";
import { deleteProjectTask } from "./FunctionCalls";
// import ListByTag from "./ListByTag";

const Home = (props) => {
    console.log("Hello Home")
    const [isSidebarOpen, setSidebarOpen] = useState(false);

    const toggleSidebar = () => {
        setSidebarOpen(!isSidebarOpen);
    };
    const [state, setState] = useState({
        searchKey: "",
        sendKey: "",
        isUpdateProjects: 1,
        notificationCount: 0,
        commentData: {},
        isUpdateList: 0,
        isUpdateNotificationList: 0,
        showCompleted: "",
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

    const getNotificationCount = useCallback(() => {
        fetch("/notification-count")
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
                    for (let c of child_ids) {
                        if (parseInt(c) === parseInt(props.value)) {
                            updateState("isUpdateList", 1);
                        } else if (parseInt(c) === 0) {
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
        Echo.channel("user" + `1fcedc560b41371faf48` + "." + `c81e728d9d4c2f636f067f89cc14862c`).listen(
            "ProjectEvent",
            (data) => {
                console.log("data :", data);
                if (data.text !== "") {
                    notification.open({
                        key: data.type,
                        message: "iTask",
                        description: data.text,
                        duration: 3,
                        className: "cursor_pointer",
                        onClick: () => {
                            notificationRedirection(data);
                        },
                    });
                    if (
                        [
                            "add_project",
                            "add_task",
                            "add_comment",
                            "member_removed_by",
                            "complete_project",
                            "complete_uncomplete",
                        ].includes(data.type)
                    ) {
                        refreshListing(data.pt_id, data.type, data);
                    }
                    getNotificationCount();
                    updateState("isUpdateNotificationList", 1);
                } else {
                    if (
                        [
                            "removed_task",
                            "refresh_list",
                            "updated_member",
                            "member_invited",
                        ].includes(data.type)
                    ) {
                        refreshListing(data.pt_id, data.type, data);
                        if (data.type === "removed_task") {
                            getNotificationCount();
                            updateState("isUpdateNotificationList", 1);
                        }
                    }
                }
            }
        );
        getNotificationCount();
    }, [getNotificationCount, notificationRedirection, refreshListing]);

    const onChange = (e) => {
        updateState(e.target.name, e.target.value);
    };

    const {
        searchKey,
        sendKey,
        isUpdateProjects,
        notificationCount,
        commentData,
        isUpdateList,
        isUpdateNotificationList,
        showCompleted,
        isSearch,
    } = state;

    const { updateSelection, section } = props;
    return (
        <React.Fragment>
            <ContextProvider
                value={{
                    toUpdate: isUpdateList,
                    setUpdate: (value) => updateState("isUpdateList", value),
                    updateSidebarProject: (value) =>
                        updateState("isUpdateProjects", value),
                    isUpdateList: isUpdateNotificationList,
                    setUpdateList: (value) =>
                        updateState("isUpdateNotificationList", value),
                    notificationCount,
                    notificationRedirection,
                    showCompleted,
                    updateCompletedState: (value) =>
                        updateState("showCompleted", value),
                    selectedValue: section === "project" ? props.value : "",
                    selectedSection: section,
                    updateSelection,
                    isSearch,
                    updateNotificationCount: getNotificationCount,
                    commentUpdatedID: state.commentProjectId,
                    documentUpdatedID: state.documentProjectId,
                    setCommentProjectID: (pt_id) =>
                        updateState("commentProjectId", pt_id),
                    setDocumentProjectID: (pt_id) =>
                        updateState("documentProjectId", pt_id),
                    setCommentDialog: (value) =>
                        updateState("isCommentDialogOpen", value),
                    setAttachmentDialog: (value) =>
                        updateState("isAttachmentDialogOpen", value),
                }}
            >
                <div className="row">
                <Navbar />
                </div>

                <div className="home" style={{height:'100vh'}}>
                    <div className={`home_sidebar ${isSidebarOpen ? 'open' : ''}`}>
                        <Sidebar isOpen={isSidebarOpen} toggleSidebar={toggleSidebar} />
                    </div>

                    <div className="home_main">
                      

                        <div className="bg_color" />
                        {/* <div className={"col-md-9 px-0"}> */}
                        <div
                            className="form-group has-search float-left mb-0 pl-4 d-flex"
                            style={{ position: "absolute", top: 5 }}
                        >
                            <input
                                type="text"
                                className="form-control"
                                onChange={onChange}
                                name="searchKey"
                            />
                            <a
                                className={"ms-2"}
                                onClick={() => {
                                    updateSelection("search", "");
                                    updateState("sendKey", searchKey);
                                    updateState("isSearch", searchKey !== "" ? 1 : 0);
                                }}
                            >
                                <img src={SearchIcon} />
                            </a>
                        </div>
                        {section === "inbox" ? (
                            // <div className="ps-4">
                            //     This is my Home
                            // </div>
                            <UnassigntaskList updateInboxCount={props.updateInboxCount} />
                            // ) : section === "search" ? (
                            //   <Search
                            //     updateSelection={updateSelection}
                            //     searchKey={sendKey}
                            //   />
                            // ) : section === "flag" ? (
                            //   <FlagwiseList
                            //     updateSelection={updateSelection}
                            //     flag={props.value}
                            //   />
                            // ) : section === "project" ? (
                            //   <ProjectdetailList
                            //     updateSelection={updateSelection}
                            //     updateEditView={props.updateEditView}
                            //     deleteProjectTask={deleteProjectTaskHandler}
                            //     updateProject={(value) =>
                            //       updateState("isUpdateProjects", value)
                            //     }
                            //     projectId={props.value}
                            //   />
                            // ) : section === "review" ? (
                            //   <ReviewProjectList updateSelection={updateSelection} />
                            // ) : section === "list_by_tag" ? (
                            // <ListByTag
                            //   tagId={props.value}
                            //   updateSelection={updateSelection}
                            // />
                        ) : (
                        //     <div className="ps-4">
                        //     This is my Home
                        // </div>
                            <UnassigntaskList updateInboxCount={props.updateInboxCount} />
                        )}
                    </div>
                    {/* <div>test</div> */}
                    {/* </div> */}
                </div>
            </ContextProvider>
        </React.Fragment>
    );
};

export default Home;