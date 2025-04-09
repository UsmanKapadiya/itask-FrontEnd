import React, { useState } from 'react';
import userIcon from "../../../public/images/icon_add_assign.png";
import { Form, message, Select } from "antd";

const AssignMembers = (props) => {
    const [members, setMembers] = useState(props.selectedMembers || []);
    const [popoverOpen, setPopoverOpen] = useState(false);

    const toggle = () => {
        setPopoverOpen(!popoverOpen);
    };

    const handleSelectChange = (value) => {
        if (count === 0) {
            props.memberCallback(value);
        }
        setMembers(value);
    };

    const handleBlur = () => {
        if (count === 0) {
            setPopoverOpen(false);
            props.tagCreation('member');
        }
    };

    const suggestions = props.memberSuggestion;
    const parentId = props.parentId;
    const setValidationCount = props.setValidationCount;

    return (
        <div className="mr-3 position-relative cursor_pointer">
            <img
                src={userIcon}
                alt="Assign member(s)"
                height="25"
                className="ml-2 cursor_pointer"
                onClick={() => {
                    parentId !== 0 ? toggle() : message.error("Please select parent project");
                }}
            />
            {popoverOpen && (
                <div className="member-selection-outer">
                    <Form
                        fields={[
                            { name: ['members'], value: members },
                        ]}
                    >
                        <Form.Item
                            name="members"
                            rules={[
                                ({ getFieldValue }) => ({
                                    validator(rule, value) {
                                        count = 0;
                                        if (parentId === undefined) {
                                            const mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
                                            for (let v in value) {
                                                if (!mailformat.test(value[v])) {
                                                    count++;
                                                }
                                            }
                                            setValidationCount(count);
                                            if (count === 0) {
                                                return Promise.resolve();
                                            } else {
                                                return Promise.reject('Invalid email-id');
                                            }
                                        } else {
                                            return Promise.resolve();
                                        }
                                    },
                                }),
                            ]}
                        >
                            <Select
                                style={{ width: '100%' }}
                                mode={parentId === undefined ? "tags" : "multiple"}
                                placeholder="Type an assignee"
                                value={members}
                                onChange={handleSelectChange}
                                optionLabelProp="label"
                                defaultOpen={false}
                                menuItemSelectedIcon={<i className="fas fa-check"></i>}
                                getPopupContainer={() =>
                                    document.getElementsByClassName('modal')[0] || document.getElementById('mainapp')
                                }
                                removeIcon={<i className="fas fa-times remove-member"></i>}
                                autoFocus={true}
                                onBlur={handleBlur}
                            >
                                {suggestions.map((member) => (
                                    <Select.Option key={member.id} value={member.email} label={member.email}>
                                        <div className="demo-option-label-item">
                                            <div
                                                className="my-0 mr-2 assign_to_avatar float-left mt-1"
                                                style={{ backgroundImage: `url(${member.avatar})` }}
                                            >
                                                &nbsp;
                                            </div>
                                            {member.name}
                                        </div>
                                    </Select.Option>
                                ))}
                            </Select>
                        </Form.Item>
                    </Form>
                </div>
            )}
        </div>
    );
};

export default AssignMembers;