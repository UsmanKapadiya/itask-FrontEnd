import React, { useState, useEffect, useCallback } from "react";
import HomeComponent from "./CommonFunctions";
import inboxImage from "../../../public/images/icon_left_inbox.png";
// import UnassigntaskList from "./UnassigntaskList";
// import FlaggedAccordion from "./FlaggedAccordion";
// import FlagwiseList from "./FlagwiseList";
// import ReviewProjectList from "./ReviewProjectList";
import reviewImage from "../../../public/images/icon_left_review.png";
import TagsAccordion from "./TagsAccordion";
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
import ProjectListAccordion from "./CompletedProjects";
import FlaggedAccordion from "./FlaggedAccordion";
// import ListByTag from "./ListByTag";

const Sidebar = (props) => {
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
  const { isUpdateProjects } = state;

  // const updateState = (key, value) => {
  //   setState((prevState) => ({ ...prevState, [key]: value }));
  // };

  // const getNotificationCount = useCallback(() => {
  //   fetch("/notification-count")
  //     .then((res) => res.json())
  //     .then(
  //       (result) => {
  //         updateState("notificationCount", result);
  //       },
  //       (error) => {
  //         console.error(error);
  //       }
  //     );
  // }, []);

  // const refreshListing = useCallback((pt_id, n_type, result) => {
  //   if (parseInt(pt_id) !== 0) {
  //     if (props.section === "project" && props.value === pt_id) {
  //       updateState("isUpdateList", 1);
  //     } else if (props.section === "inbox" || props.section === "project") {
  //       if (result.all_ids !== undefined) {
  //         let child_ids = result.all_ids.split(",");
  //         for (let c of child_ids) {
  //           if (parseInt(c) === parseInt(props.value)) {
  //             updateState("isUpdateList", 1);
  //           } else if (parseInt(c) === 0) {
  //             updateState("isUpdateList", 1);
  //             props.updateInboxCount();
  //           }
  //         }
  //       }
  //     }
  //     if (
  //       ["review", "flag", "search", "list_by_tag"].includes(props.section)
  //     ) {
  //       updateState("isUpdateList", 1);
  //     }
  //   } else {
  //     if (props.section === "inbox") {
  //       updateState("isUpdateList", 1);
  //     } else {
  //       props.updateInboxCount();
  //     }
  //   }
  //   if (
  //     [
  //       "add_project",
  //       "add_task",
  //       "complete_project",
  //       "complete_uncomplete",
  //       "removed_task",
  //       "refresh_list",
  //       "delete_project_task",
  //     ].includes(n_type)
  //   ) {
  //     updateState("isUpdateProjects", 1);
  //   }
  // }, [props]);

  // const notificationRedirection = useCallback((data) => {
  //   if (
  //     [
  //       "add_project",
  //       "add_task",
  //       "add_comment",
  //       "member_removed_by",
  //       "complete_project",
  //       "complete_uncomplete",
  //       "reminder",
  //     ].includes(data.type)
  //   ) {
  //     if (data.type === "complete_uncomplete") {
  //       updateState("showCompleted", 1);
  //     }
  //     if (parseInt(data.pt_id) !== 0) {
  //       props.updateSelection("project", data.pt_id);
  //     } else {
  //       props.updateSelection("inbox", "1");
  //     }
  //     if (data.type === "add_comment") {
  //       updateState("commentData", data.display_data);
  //     }
  //   }
  //   if (data.type === "member_removed") {
  //     message.error(data.slient_msg);
  //   }
  // }, [props]);

  // const deleteProjectTaskHandler = (id, type, popoverRef) => {
  //   popoverRef.current.props.onPopupVisibleChange(false);
  //   confirmAlert({
  //     title: `Delete ${type}`,
  //     message: "Are you sure want to delete?",
  //     buttons: [
  //       {
  //         label: "Yes",
  //         onClick: () => {
  //           const data = { projectTaskId: id };
  //           deleteProjectTask(data).then((res) => {
  //             if (res.errorStatus) {
  //               message.error(res.data);
  //             } else {
  //               message.success(res.data, 3);
  //             }
  //           });
  //         },
  //       },
  //       {
  //         label: "No",
  //       },
  //     ],
  //   });
  // };

  // useEffect(() => {
  //   Pusher.logToConsole = false;
  //   Echo.channel("user" + `1fcedc560b41371faf48` + "." + `c81e728d9d4c2f636f067f89cc14862c`).listen(
  //     "ProjectEvent",
  //     (data) => {
  //       console.log("data :", data);
  //       if (data.text !== "") {
  //         notification.open({
  //           key: data.type,
  //           message: "iTask",
  //           description: data.text,
  //           duration: 3,
  //           className: "cursor_pointer",
  //           onClick: () => {
  //             notificationRedirection(data);
  //           },
  //         });
  //         if (
  //           [
  //             "add_project",
  //             "add_task",
  //             "add_comment",
  //             "member_removed_by",
  //             "complete_project",
  //             "complete_uncomplete",
  //           ].includes(data.type)
  //         ) {
  //           refreshListing(data.pt_id, data.type, data);
  //         }
  //         getNotificationCount();
  //         updateState("isUpdateNotificationList", 1);
  //       } else {
  //         if (
  //           [
  //             "removed_task",
  //             "refresh_list",
  //             "updated_member",
  //             "member_invited",
  //           ].includes(data.type)
  //         ) {
  //           refreshListing(data.pt_id, data.type, data);
  //           if (data.type === "removed_task") {
  //             getNotificationCount();
  //             updateState("isUpdateNotificationList", 1);
  //           }
  //         }
  //       }
  //     }
  //   );
  //   getNotificationCount();
  // }, [getNotificationCount, notificationRedirection, refreshListing]);

  // const onChange = (e) => {
  //   updateState(e.target.name, e.target.value);
  // };

  // const {
  //   searchKey,
  //   sendKey,
  //   isUpdateProjects,
  //   notificationCount,
  //   commentData,
  //   isUpdateList,
  //   isUpdateNotificationList,
  //   showCompleted,
  //   isSearch,
  // } = state;

  // const { updateSelection, section } = props;

  const deleteProjectTaskHandler = (id, type, popoverRef) => {
    popoverRef.current.props.onPopupVisibleChange(false);
    confirmAlert({
      title: `Delete ${type}`,
      message: "Are you sure you want to delete?",
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
                // Update the state or refresh the list if needed
                setState((prevState) => ({
                  ...prevState,
                  isUpdateProjects: 1,
                }));
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

  const updateSelection = (section, value) => {
    console.log(`Selected section: ${section}, value: ${value}`);
    // Add logic to handle the selection update
    setState((prevState) => ({
      ...prevState,
      selectedSection: section,
      selectedValue: value,
    }));
  };
  
  const updateState = (key, value) => {
    setState((prevState) => ({
      ...prevState,
      [key]: value, // Dynamically update the state based on the key
    }));
  };
  return (
    <React.Fragment>
      {/* <ContextProvider
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
      > */}
        <div id="header">
          {/* <Header /> */}
        </div>
        <div className={"row "} style={{backgroundColor:'pink',height:'100vh'}}>
          {/* <div className={"col-md-3 px-0"}> */}
            <div className="sidebar h-100 py-4">
              <ul className="nav-bar flex-column">
                <li className="nav-item border-bottom pb-2">
                  <a
                    className="d-flex align-items-center nav-link active"
                    href="#"
                    onClick={() => {
                      updateSelection("inbox", "1");
                    }}
                  >
                    <img src={inboxImage} alt="Inbox" className="mr-2" />
                    <div className={"ps-2"}>Inbox</div>
                    <span className={"total-count ms-2"}>{props.inboxCount}</span>
                  </a>
                </li>
                 <li className="nav-item border-bottom py-2 pb-2">
                  <ProjectListAccordion
                    isUpdateProjects={isUpdateProjects}
                    is_completed={0}
                    deleteProjectTask={deleteProjectTaskHandler}
                    updateSelection={updateSelection}
                    updateEditView={props.updateEditView}
                    updateProject={(value) =>
                      updateState("isUpdateProjects", value)
                    }
                  />
                </li>
               <li className="nav-item border-bottom py-2 pb-2">
                  <span className="color-black font-weight-bold">
                    <FlaggedAccordion {...props} />
                  </span>
                </li>
              <li className="nav-item border-bottom py-2 pb-2">
                  <a
                    className="nav-link active"
                    href="#"
                    onClick={() => {
                      updateSelection("review", "1");
                    }}
                  >
                    <img src={reviewImage} alt="Review" className={"mr-2 me-2"} />
                    Review
                  </a>
                </li>
                 <li className="nav-item border-bottom py-2 pb-2">
                  <TagsAccordion updateSelection={updateSelection} />
                </li>
                <li className="nav-item border-bottom py-2 ">
                  <ProjectListAccordion
                    isUpdateProjects={isUpdateProjects}
                    is_completed={1}
                    deleteProjectTask={deleteProjectTaskHandler}
                    updateSelection={updateSelection}
                    updateEditView={props.updateEditView}
                    updateProject={(value) =>
                      updateState("isUpdateProjects", value)
                    }
                  />
                </li>
              </ul>
            </div>
          {/* </div> */}
       
          {/* {props.isEditView ? (
            <AddProjectModal
              isEdit={props.isEditView}
              projectId={props.editProjectId}
              updateEditView={() => {
                props.updateEditView(0, "");
              }}
            />
          ) : (
            ""
          )}
          {commentData.project_id !== undefined ? (
            <Comment
              modelOpen={true}
              ptId={commentData.project_id}
              name={commentData.project_name}
              projectname={""}
              setCommentData={() => updateState("commentData", {})}
            />
          ) : (
            ""
          )} */}
        </div>
      {/* </ContextProvider> */}
    </React.Fragment>
  );
};

export default HomeComponent(Sidebar);