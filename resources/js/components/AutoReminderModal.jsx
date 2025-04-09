import React, { useState, useEffect } from 'react';
import { Button, Modal, ModalHeader, ModalBody } from 'reactstrap';
import { message, Select } from "antd";
import { updateautoreminder } from "./FunctionCalls";
import * as options from './Constants';

const { Option } = Select;

const AutoReminder = () => {
    const [modal, setModal] = useState(false);
    const [userAutoReminder, setUserAutoReminder] = useState('');
    const [errors, setErrors] = useState(null);

    useEffect(() => {
        fetch('/member-notification-information')
            .then((res) => res.json())
            .then(
                (result) => {
                    setUserAutoReminder(result.automatic_reminder);
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
        const autoReminder = new FormData();
        autoReminder.append('name', userAutoReminder);

        updateautoreminder(autoReminder).then((res) => {
            if (res.errorStatus) {
                setErrors(res.data);
            } else {
                setModal(false);
                message.success(res.data);
            }
        });
    };

    return (
        <div>
            <a onClick={toggle} style={{ cursor: 'pointer' }}>
                Send automatic reminders
            </a>
            <Modal isOpen={modal} toggle={toggle}>
                <ModalHeader toggle={toggle} className="border-0 text-uppercase">
                    Send Automatic Reminders
                </ModalHeader>
                <ModalBody>
                    <div>
                        <Select
                            showSearch
                            name="userAutoReminder"
                            style={{ width: 200 }}
                            placeholder="Select Automatic Reminder"
                            optionFilterProp="children"
                            value={userAutoReminder}
                            onChange={(value) => setUserAutoReminder(value)}
                            filterOption={(input, option) =>
                                option.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
                            }
                        >
                            {options.reminderOptions.map((reminder, index) => (
                                <Option key={index} value={reminder} label={reminder}>
                                    {reminder}
                                </Option>
                            ))}
                        </Select>
                        <div className="text-right mt-3">
                            <Button color="primary" onClick={handleSave}>
                                Save
                            </Button>
                        </div>
                    </div>
                </ModalBody>
            </Modal>
        </div>
    );
};

export default AutoReminder;