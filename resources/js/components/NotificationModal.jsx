import React, { useState, useEffect } from 'react';
import {
    Button,
    Modal,
    ModalHeader,
    ModalBody,
} from 'reactstrap';
import emailNotification from '../../../public/images/icon_notification_email.png';
import mobileNotification from '../../../public/images/icon_notification_mobile.png';
import { updateNotificationSetting } from "./FunctionCalls";
import { message } from "antd";

const NotificationModal = () => {
    const [modal, setModal] = useState(false);
    const [data, setData] = useState([]);
    const [notificationSettings, setNotificationSettings] = useState({});

    useEffect(() => {
        fetch('/notification-information')
            .then((res) => res.json())
            .then(
                (result) => {
                    setData(result.notification_settings);
                    const settings = {};
                    result.notification_settings.forEach((item) => {
                        settings[item.key_val] = {
                            email: item.email,
                            push_notification: item.push_notification,
                        };
                    });
                    setNotificationSettings(settings);
                },
                (error) => {
                    console.error('Error fetching notification information:', error);
                }
            );
    }, []);

    const toggle = () => {
        setModal(!modal);
    };

    const handleSave = (e) => {
        e.preventDefault();
        const NotificationSetting = new FormData();
        for (const key in notificationSettings) {
            NotificationSetting.append(key, JSON.stringify(notificationSettings[key]));
        }

        updateNotificationSetting(NotificationSetting).then((res) => {
            if (res.errorStatus) {
                message.error(res.data);
            } else {
                setModal(false);
                message.success(res.data);
            }
        });
    };

    const handleCheckboxChange = (key, field, value) => {
        setNotificationSettings((prevSettings) => ({
            ...prevSettings,
            [key]: {
                ...prevSettings[key],
                [field]: value,
            },
        }));
    };

    return (
        <div>
            <a onClick={toggle} style={{ cursor: 'pointer' }}>
                Notification
            </a>
            <Modal isOpen={modal} toggle={toggle}>
                <ModalHeader toggle={toggle} className="border-0 text-uppercase">
                    Notification
                </ModalHeader>
                <ModalBody>
                    <div className="d-flex justify-content-between border-bottom my-2">
                        <div className="w-75">
                            <div className="text-uppercase">Notify me about</div>
                        </div>
                        <div>
                            <span className="mr-2">
                                <img src={emailNotification} style={{ height: 15, width: 20 }} alt="Email Notification" />
                            </span>
                            <span>
                                <img src={mobileNotification} style={{ height: 15, width: 20 }} alt="Mobile Notification" />
                            </span>
                        </div>
                    </div>
                    {data.map((individual) => (
                        <div className="d-flex justify-content-between my-2" key={individual.key_val}>
                            <div className="w-75">{individual.title}</div>
                            <div>
                                <span className="mr-4">
                                    <input
                                        className="form-check-input"
                                        type="checkbox"
                                        onChange={(e) =>
                                            handleCheckboxChange(individual.key_val, 'email', e.target.checked ? 1 : 0)
                                        }
                                        checked={notificationSettings[individual.key_val]?.email || false}
                                    />
                                </span>
                                <span>
                                    <input
                                        className="form-check-input"
                                        type="checkbox"
                                        onChange={(e) =>
                                            handleCheckboxChange(individual.key_val, 'push_notification', e.target.checked ? 1 : 0)
                                        }
                                        checked={notificationSettings[individual.key_val]?.push_notification || false}
                                    />
                                </span>
                            </div>
                        </div>
                    ))}
                    <div className="text-right mt-3">
                        <Button color="primary" onClick={handleSave}>
                            Save
                        </Button>
                    </div>
                </ModalBody>
            </Modal>
        </div>
    );
};

export default NotificationModal;