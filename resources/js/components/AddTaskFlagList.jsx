import React, { useState } from 'react';
import { Select } from 'antd';
import * as options from './Constants';

const { Option } = Select;
const priorityFlags = options.priorityFlags;

const Flaglist = (props) => {
    const [popoverOpen, setPopoverOpen] = useState(false);
    const [flag, setFlag] = useState(props.priority ? props.priority.toString() : "4");

    const toggle = () => {
        setPopoverOpen(!popoverOpen);
    };

    const handleChange = (value) => {
        props.flagCallback(value);
        setFlag(value);
    };

    const handleBlur = () => {
        setPopoverOpen(false);
    };

    return (
        <div className="mr-3 position-relative cursor_pointer">
            <a onClick={toggle}>
                <img
                    src={priorityFlags[flag].image}
                    alt="Add priority"
                    height="30"
                    data-toggle="tooltip"
                    title="Add priority"
                />
            </a>
            {popoverOpen && (
                <div className="flag-selection-outer">
                    <Select
                        style={{ width: '100%' }}
                        placeholder="Select priority"
                        defaultValue={flag}
                        onChange={handleChange}
                        optionLabelProp="label"
                        showSearch={false}
                        defaultOpen={true}
                        open={true}
                        menuItemSelectedIcon={<i className="fas fa-check"></i>}
                        autoFocus={true}
                        onBlur={handleBlur}
                    >
                        {Object.keys(priorityFlags).map((f) => (
                            <Option value={priorityFlags[f].value} label={priorityFlags[f].label} key={f}>
                                <div className="demo-option-label-item">
                                    <img
                                        src={priorityFlags[f].image}
                                        alt={priorityFlags[f].label}
                                        className="mr-2"
                                        height="30"
                                    />
                                    {priorityFlags[f].label}
                                </div>
                            </Option>
                        ))}
                    </Select>
                </div>
            )}
        </div>
    );
};

export default Flaglist;