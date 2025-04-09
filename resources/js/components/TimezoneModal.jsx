import React, { useState, useEffect } from 'react';
import {
    Button,
    Modal,
    ModalHeader,
    ModalBody,
} from 'reactstrap';
import { message, Select } from 'antd';
import { updatetimezone } from './FunctionCalls';

const { Option } = Select;

const TimezoneModal = () => {
    const [modal, setModal] = useState(false);
    const [timezonelist, setTimezonelist] = useState([]);
    const [membertimezone, setMembertimezone] = useState('');
    const [errors, setErrors] = useState(null);

    useEffect(() => {
        fetch('/member-information')
            .then((res) => res.json())
            .then(
                (result) => {
                    setTimezonelist(result.timezonelist);
                    setMembertimezone(result.usertimezone);
                },
                (error) => {
                    console.error('Error fetching timezone data:', error);
                }
            );
    }, []);

    const toggle = () => {
        setModal(!modal);
    };

    const handleSave = (e) => {
        e.preventDefault();
        const Timezone = new FormData();
        Timezone.append('name', membertimezone);

        updatetimezone(Timezone).then((res) => {
            if (res.errorStatus) {
                setErrors(res.data);
            } else {
                setModal(false);
                message.success(res.data, () => {
                    window.location.reload();
                });
            }
        });
    };

    return (
        <div>
            <a onClick={toggle} style={{ cursor: 'pointer' }}>
                Timezone
            </a>
            <Modal isOpen={modal} toggle={toggle}>
                <ModalHeader toggle={toggle} className="border-0">
                    Timezone
                </ModalHeader>
                <ModalBody>
                    <div>
                        <Select
                            showSearch
                            name="membertimezone"
                            style={{ width: 200 }}
                            placeholder="Select Timezone"
                            optionFilterProp="children"
                            value={membertimezone}
                            onChange={(value) => setMembertimezone(value)}
                            filterOption={(input, option) =>
                                option.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
                            }
                        >
                            {timezonelist.map((timezone) => (
                                <Option value={timezone} label={timezone} key={timezone}>
                                    {timezone}
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

export default TimezoneModal;