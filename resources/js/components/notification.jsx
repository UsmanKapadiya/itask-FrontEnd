import React, { useState, useEffect, useRef, useContext } from 'react';
import notificationImage from '../../../public/images/icon_nav_notification.png';
import { Popover, Tooltip } from "antd";
import { Readnotification } from "./FunctionCalls";
import ProjectContext from "./projectContext";

const Notificationlist = () => {
    const [data, setData] = useState([]);
    const [individualId, setIndividualId] = useState('');
    const [errors, setErrors] = useState({});
    const popoverRef = useRef(null);
    const context = useContext(ProjectContext);

    useEffect(() => {
        getData();

        if (context.isUpdateList) {
            context.setUpdateList(0);
        }
    }, [context.isUpdateList]);

    const getData = () => {
        fetch("/notification-list")
            .then((res) => res.json())
            .then(
                (result) => {
                    setData(result);
                },
                (error) => {
                    console.error("Failed to fetch notifications:", error);
                }
            );
    };

    const handleMarkAsRead = (e) => {
        e.stopPropagation();
        const notificationId = e.target.getAttribute("datavalue");
        setIndividualId(notificationId);

        const Notification = new FormData();
        Notification.append('id', notificationId);

        Readnotification(Notification).then((res) => {
            if (res.errorStatus) {
                setErrors(res.data);
            } else {
                getData();
                context.updateNotificationCount();
            }
        });
    };

    const notificationCount = context.notificationCount;

    return (
        <Popover
            ref={popoverRef}
            content={
                <div className="notification-list">
                    <div className="font-weight-bold mb-3 text-center">Notifications</div>
                    {data.length > 0 ? (
                        data.map((individual) => (
                            <div
                                key={individual.id}
                                className={`d-flex p-2 cursor_pointer position-relative border-bottom ${
                                    individual.is_read === 1 ? 'bg-white' : 'bg-grey'
                                }`}
                                onClick={() => {
                                    popoverRef.current.props.onPopupVisibleChange(false);
                                    context.notificationRedirection(individual.data);
                                }}
                            >
                                <div
                                    className="my-0 mr-2 notification-avatar-img"
                                    style={{ backgroundImage: `url(${individual.sent_by_avatar})` }}
                                >
                                    &nbsp;
                                </div>
                                <div className="w-75">
                                    <div className="color-navyblue">{individual.notification_text}</div>
                                    <div className="color-navyblue">{individual.data_to_display}</div>
                                    <div className="color-nobel">{individual.sent_time}</div>
                                </div>
                                {individual.is_read === 0 && (
                                    <div className="mark-as-read">
                                        <Tooltip title="Mark as Read">
                                            <input
                                                type="radio"
                                                className="cursor_pointer mr-2"
                                                value={individual.id}
                                                onChange={handleMarkAsRead}
                                                datavalue={individual.id}
                                            />
                                        </Tooltip>
                                    </div>
                                )}
                            </div>
                        ))
                    ) : (
                        <div>No notifications available</div>
                    )}
                </div>
            }
            trigger="click"
            placement="topLeft"
        >
            <div className="d-inline-block notification mr-4 cursor_pointer notification-avatar">
                <img src={notificationImage} alt="Notifications" />
                <span className="notification-count">{notificationCount}</span>
            </div>
        </Popover>
    );
};

export default Notificationlist;