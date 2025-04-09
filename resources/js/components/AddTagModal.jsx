import React, { useState } from 'react';
import { Button, Modal, ModalHeader, ModalBody, ModalFooter } from 'reactstrap';
import { addTag } from './FunctionCalls';
import plusIcon from "../../../public/images/icon_left_add.png";
import { message } from "antd";

const AddTagModal = ({ refreshData }) => {
    const [modal, setModal] = useState(false);
    const [tag, setTag] = useState('');

    const toggle = (e) => {
        e.stopPropagation();
        setModal((prevModal) => !prevModal);
    };

    const handleAdd = () => {
        const newTag = { tagname: tag };

        addTag(newTag).then((res) => {
            if (res.errorStatus) {
                message.error(res.data);
            } else {
                setModal(false);
                message.success(res.data);
                refreshData();
            }
        });
    };

    return (
        <>
            <a onClick={toggle} style={{ cursor: "pointer" }}>
                <img src={plusIcon} alt="Add Tag" />
            </a>
            <Modal isOpen={modal} className="AddProject">
                <ModalHeader>Add Tag</ModalHeader>
                <ModalBody>
                    <p className="font-weight-bold mb-0">Tag name</p>
                    <input
                        type="text"
                        className="form-control w-100 mb-2"
                        onChange={(e) => setTag(e.target.value)}
                        name="tag"
                        placeholder="Enter Tag"
                    />
                </ModalBody>
                <ModalFooter>
                    <Button color="secondary" onClick={toggle}>
                        Cancel
                    </Button>
                    <Button color="primary" onClick={handleAdd}>
                        Add
                    </Button>
                </ModalFooter>
            </Modal>
        </>
    );
};

export default AddTagModal;