import React, { useRef } from 'react';
import settingImage from '../../../public/images/icon_nav_setting.png';
import { Popover, Menu, message } from "antd";
import AccountModal from './updateaccountmodal';
import TimezoneModal from "./TimezoneModal";
import RemindMeVia from "./RemindmeviaModal";
import AutoReminder from "./AutoReminderModal";
import NotificationModal from "./NotificationModal";
import { confirmAlert } from "react-confirm-alert";
import { deleteAccount } from "./FunctionCalls";

const { SubMenu } = Menu;

const Settingmenu = () => {
    const popoverRef = useRef(null);

    const handleClick = () => {
        popoverRef.current.props.onPopupVisibleChange(false);
    };

    const handleDeleteAccount = () => {
        confirmAlert({
            title: 'Delete account',
            message: 'Are you sure you want to delete?',
            buttons: [
                {
                    label: 'Yes',
                    onClick: () => {
                        deleteAccount().then((res) => {
                            if (res.errorStatus) {
                                message.error(res.data);
                            } else {
                                window.location = "/login";
                            }
                        });
                    },
                },
                {
                    label: 'No',
                },
            ],
        });
    };

    return (
        <Popover
            ref={popoverRef}
            content={
                <Menu
                    style={{ width: 256 }}
                    defaultSelectedKeys={['1']}
                    defaultOpenKeys={['sub1']}
                    mode="inline"
                >
                    <Menu.Item>
                        <div className="cursor_pointer mb-1" onClick={handleClick}>
                            <AccountModal />
                        </div>
                    </Menu.Item>
                    <SubMenu key="sub1" title="General">
                        <Menu.Item>
                            <div className="cursor_pointer mb-1" onClick={handleClick}>
                                <TimezoneModal />
                            </div>
                        </Menu.Item>
                        <SubMenu key="sub2" title="Reminders">
                            <Menu.Item>
                                <div className="cursor_pointer mb-1" onClick={handleClick}>
                                    <RemindMeVia />
                                </div>
                            </Menu.Item>
                            <Menu.Item>
                                <div className="cursor_pointer mb-1" onClick={handleClick}>
                                    <AutoReminder />
                                </div>
                            </Menu.Item>
                        </SubMenu>
                        <Menu.Item>
                            <div className="cursor_pointer mb-1" onClick={handleClick}>
                                <NotificationModal />
                            </div>
                        </Menu.Item>
                    </SubMenu>
                    <Menu.Item>
                        <div className="cursor_pointer mb-1" onClick={handleDeleteAccount}>
                            Delete account
                        </div>
                    </Menu.Item>
                </Menu>
            }
            trigger="click"
            placement="topLeft"
        >
            <span className="cursor_pointer">
                <img src={settingImage} alt="Options" />
            </span>
        </Popover>
    );
};

export default Settingmenu;