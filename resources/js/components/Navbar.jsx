import React, { useContext, useState } from 'react';
import { Link } from 'react-router-dom';
import AccountCircleIcon from '@mui/icons-material/AccountCircle';
import BarChartIcon from '@mui/icons-material/BarChart';
import ChatBubbleOutlineIcon from '@mui/icons-material/ChatBubbleOutline';
import CloseIcon from '@mui/icons-material/Close';
import CreditCardIcon from '@mui/icons-material/CreditCard';
import DarkModeIcon from '@mui/icons-material/DarkMode';
import DashboardIcon from '@mui/icons-material/Dashboard';
import FullscreenExitIcon from '@mui/icons-material/FullscreenExit';
import LanguageIcon from '@mui/icons-material/Language';
import LightModeIcon from '@mui/icons-material/LightMode';
import LogoutIcon from '@mui/icons-material/Logout';
import MenuIcon from '@mui/icons-material/Menu';
import NotificationsNoneIcon from '@mui/icons-material/NotificationsNone';
import PersonIcon from '@mui/icons-material/Person';
import SearchIcon from '@mui/icons-material/Search';
import SettingsRoundedIcon from '@mui/icons-material/SettingsRounded';
import TableChartIcon from '@mui/icons-material/TableChart';
import './navbar.scss';
import LogoImage from '../../../public/images/logo_itask.png'


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
// import Search from "./Search";
import Header from "./Header";
import { message, notification } from "antd";
import { ContextProvider } from "./projectContext";
// import Comment from "./Comment";
import { confirmAlert } from "react-confirm-alert";
import { deleteProjectTask } from "./FunctionCalls";
import ProjectListAccordion from "./CompletedProjects";
import FlaggedAccordion from "./FlaggedAccordion";
import AddTaskModal from './AddTaskModal';
import Notificationlist from './notification';
import Settingmenu from './setting';
// import ListByTag from "./ListByTag";

// function Navbar() {
    const Navbar = (props) => {
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
    const [toggle, setToggle] = useState(false);

    const handleToggle = () => {
        setToggle(!toggle);
    };

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
        <div className="navbar mb-0">
            <div className="navbar_main">
                <div className="menu_logo">
                    {toggle ? (
                        <CloseIcon className="menu_icon" onClick={handleToggle} />
                    ) : (
                        <MenuIcon className="menu_icon" onClick={handleToggle} />
                    )}

                    {/* <Link to="/" style={{ textDecoration: 'none' }}>
                        <h3 className="text_none">Dashboard</h3>
                    </Link> */}
                </div>
                <div>
                    <img src={LogoImage} alt="itask" />

                </div>
                {/* <div className="search">
                    <input type="text" placeholder="Search.." />
                    <SearchIcon className="search_icon" />
                </div> */}

                <div className="item_lists">
                    {/* <div className="item item_lan">
                        <LanguageIcon className="item_icon" />
                        <p>English</p>
                    </div>
                    <div className="item">
                        <LightModeIcon className="item_icon white" />
                    </div>
                    <div className="item">
                        <FullscreenExitIcon className="item_icon" />
                    </div>
                    <div className="item">
                        <ChatBubbleOutlineIcon className="item_icon" />
                        <span className="badge">2</span>
                    </div>
                    <div className="item">
                        <NotificationsNoneIcon className="item_icon" />
                        <span className="badge">1</span>
                    </div>
                    <div className="item">
                        <img className="admin_pic" src={'https://st2.depositphotos.com/1002277/10073/i/450/depositphotos_100732302-stock-photo-word-admin-on-wood-planks.jpg'} alt="admin" />
                    </div> */}
                     <div className="float-right d-flex h-100">
                                <div className="align-self-center">
                                    <div className="d-inline-block me-4 add-task">
                                        <AddTaskModal isHeader={1}/>
                                    </div>
                                    <span className='me-4'>
                                        <Notificationlist/>
                                    </span>
                                    <div className="d-inline-block me-4 add-task">
                                        <Settingmenu/>
                                    </div>
                                    <a href="#" onClick={() => {
                                        localStorage.setItem("section", null)
                                        localStorage.setItem("value", null)
                                        window.location = "/logout"
                                    }}>
                                        <span className="d-inline logout">
                                          <i className="fas fa-sign-out-alt fa-lg color-white ms-4"></i>
                                        </span>
                                    </a>
                                </div>
                            </div>
                </div>
            </div>

            <div className="res_navbar">
                {toggle && (
                    <div className="res_nav_menu">
                        <div className="res_nav_menuu">
                            <div className="links">
                                {/* <ul>
                                    <p className="spann">Main</p>
                                    <Link to="/" style={{ textDecoration: 'none' }}>
                                        <li>
                                            <DashboardIcon className="icon" /> Dashboard
                                        </li>
                                    </Link>

                                    <p className="spann">Lists</p>
                                    <Link to="/users" style={{ textDecoration: 'none' }}>
                                        <li>
                                            <PersonIcon className="icon" /> Users
                                        </li>
                                    </Link>

                                    <Link to="/products" style={{ textDecoration: 'none' }}>
                                        <li>
                                            <TableChartIcon className="icon" /> Products
                                        </li>
                                    </Link>
                                    <Link to="/orders" style={{ textDecoration: 'none' }}>
                                        <li>
                                            <CreditCardIcon className="icon" /> Orders
                                        </li>
                                    </Link>
                                    <li>
                                        <CreditCardIcon className="icon" /> Balance
                                    </li>
                                    <li>
                                        <BarChartIcon className="icon" /> Status
                                    </li>

                                    <p className="spann">Settings</p>
                                    <li>
                                        <AccountCircleIcon className="icon" /> Profile
                                    </li>
                                    <li>
                                        <SettingsRoundedIcon className="icon" /> Setting
                                    </li>
                                    <li>
                                        <LogoutIcon className="icon" /> Log Out
                                    </li>
                                </ul> */}
                                <ul className="nav-bar flex-column ps-0">
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
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

export default Navbar;