import React, { useState, useEffect } from 'react';
import { message, Select } from 'antd';
import reminderIcon from '../../../public/images/icon_function_reminder.png';
import * as options from './Constants';

const { Option } = Select;
const reminderOptions = options.reminderOptions;

const Reminder = (props) => {
    const [popoverOpen, setPopoverOpen] = useState(false);
    const [reminder, setReminder] = useState(props.reminder ? props.reminder.toString() : "None");

    useEffect(() => {
        if (props.reminder !== reminder) {
            setReminder(props.reminder);
        }
    }, [props.reminder]);

    const toggle = () => {
        if (props.selectedDueDate === "") {
            message.error("Please select Date first to set Reminder");
        } else {
            setPopoverOpen(!popoverOpen);
        }
    };

    const handleChange = (value) => {
        props.reminderCallback(value);
        setReminder(value);
    };

    const handleBlur = () => {
        setPopoverOpen(false);
        props.tagCreation('reminder');
    };

    return (
        <div className="mr-3 position-relative cursor_pointer">
            <a onClick={toggle}>
                <img src={reminderIcon} alt="Reminder" data-toggle="tooltip" title="Add Reminder" />
            </a>
            {popoverOpen && (
                <div className="flag-selection-outer">
                    <Select
                        style={{ width: '100%' }}
                        placeholder="Select reminder"
                        value={reminder}
                        onChange={handleChange}
                        optionLabelProp="label"
                        showSearch={false}
                        defaultOpen={true}
                        open={true}
                        menuItemSelectedIcon={<i className="fas fa-check"></i>}
                        autoFocus={true}
                        onBlur={handleBlur}
                    >
                        {reminderOptions.map((reminderOption, index) => (
                            <Option key={index} value={reminderOption} label={reminderOption}>
                                <div className="demo-option-label-item">{reminderOption}</div>
                            </Option>
                        ))}
                    </Select>
                </div>
            )}
        </div>
    );
};

export default Reminder;