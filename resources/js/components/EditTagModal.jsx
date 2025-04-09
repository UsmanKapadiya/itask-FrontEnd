import React, { useState } from 'react';
import { Button, Modal, ModalHeader, ModalBody, ModalFooter } from 'reactstrap';
import { editTag } from './FunctionCalls';
import editImage from '../../../public/images/icon_function_edit.png';
import { message } from "antd";

const EditTagModal = ({ name, id, popRef, refreshData }) => {
    const [modal, setModal] = useState(false);
    const [tag, setTag] = useState(name);

    const toggle = (e) => {
        popRef.current.props.onPopupVisibleChange(false);
        setModal((prevModal) => !prevModal);
    };

    const handleSave = () => {
        const updatedTag = {
            tagname: tag,
            tagid: id,
        };

        editTag(updatedTag).then((res) => {
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
        <div>
            <a onClick={toggle} className="cursor_pointer">
                <img src={editImage} className="mr-2" alt="Edit" />
                Edit tag
            </a>
            <Modal isOpen={modal} className="AddProject">
                <ModalHeader>Edit Tag</ModalHeader>
                <ModalBody>
                    <p className="font-weight-bold mb-0">Tag name</p>
                    <input
                        type="text"
                        className="form-control w-100 mb-2"
                        onChange={(e) => setTag(e.target.value)}
                        name="tag"
                        value={tag}
                    />
                </ModalBody>
                <ModalFooter>
                    <Button color="secondary" onClick={toggle}>
                        Cancel
                    </Button>
                    <Button color="primary" onClick={handleSave}>
                        Save
                    </Button>
                </ModalFooter>
            </Modal>
        </div>
    );
};

export default EditTagModal;