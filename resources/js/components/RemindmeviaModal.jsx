import React, { useState, useEffect } from 'react';
import { Button, Modal, ModalHeader, ModalBody } from 'reactstrap';
import { updateReminderNotification } from './FunctionCalls';
import { message } from 'antd';

const RemindMeVia = () => {
    const [modal, setModal] = useState(false);
    const [viaEmail, setViaEmail] = useState(false);
    const [viaMobile, setViaMobile] = useState(false);
    const [viaDesktop, setViaDesktop] = useState(false);

    useEffect(() => {
        fetch('/member-notification-information')
            .then((res) => res.json())
            .then(
                (result) => {
                    setViaEmail(result.remind_via_email === 1);
                    setViaMobile(result.remind_via_mobile === 1);
                    setViaDesktop(result.remind_via_desktop === 1);
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
        const ReminderNotification = new FormData();
        ReminderNotification.append('mobile', viaMobile);
        ReminderNotification.append('desktop', viaDesktop);
        ReminderNotification.append('email', viaEmail);

        updateReminderNotification(ReminderNotification).then((res) => {
            if (res.errorStatus) {
                message.error(res.data);
            } else {
                setModal(false);
                message.success(res.data);
            }
        });
    };

    return (
        <div>
            <a onClick={toggle} style={{ cursor: 'pointer' }}>
                Remind me via
            </a>
            <Modal isOpen={modal} toggle={toggle}>
                <ModalHeader toggle={toggle} className="border-0 text-uppercase">
                    Remind me via
                </ModalHeader>
                <ModalBody>
                    <div className="d-flex">
                        <div className="w-75">
                            <div>Mobile Push Notification</div>
                        </div>
                        <div className="form-check">
                            <input
                                className="form-check-input"
                                type="checkbox"
                                onChange={() => setViaMobile(!viaMobile)}
                                checked={viaMobile}
                            />
                        </div>
                    </div>
                    <div className="d-flex">
                        <div className="w-75">
                            <div>Desktop Push Notification</div>
                        </div>
                        <div className="form-check">
                            <input
                                className="form-check-input"
                                type="checkbox"
                                onChange={() => setViaDesktop(!viaDesktop)}
                                checked={viaDesktop}
                            />
                        </div>
                    </div>
                    <div className="d-flex">
                        <div className="w-75">
                            <div>Email</div>
                        </div>
                        <div className="form-check">
                            <input
                                className="form-check-input"
                                type="checkbox"
                                onChange={() => setViaEmail(!viaEmail)}
                                checked={viaEmail}
                            />
                        </div>
                    </div>
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

export default RemindMeVia;