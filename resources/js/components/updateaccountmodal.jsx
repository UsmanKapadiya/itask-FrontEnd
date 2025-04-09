import React, { useState, useEffect, useRef } from 'react';
import {
    Button,
    Modal,
    ModalHeader,
    ModalBody,
    ModalFooter,
    Alert,
} from 'reactstrap';
import Submit from "../../../public/images/btn_next.png";
import { updateuser } from "./FunctionCalls";
import { message, Popover, Popconfirm } from "antd";
import addprofile from "../../../public/images/add_profile.png";
import editprofile from "../../../public/images/icon_camera.png";

const AccountModal = () => {
    const [modal, setModal] = useState(false);
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [file, setFile] = useState('');
    const [previewURL, setPreviewURL] = useState('');
    const [editProfile, setEditProfile] = useState(false);
    const [errors, setErrors] = useState({});
    const popoverRef = useRef(null);

    const toggle = () => {
        setModal(!modal);
    };

    const getMemberDetail = () => {
        setErrors({});
        fetch('/member-information')
            .then((res) => res.json())
            .then(
                (result) => {
                    setName(result.name);
                    setEmail(result.email);
                    setPreviewURL(result.avatar);
                },
                (error) => {
                    console.error("Error fetching member details:", error);
                }
            );
    };

    const handleImageChange = (e) => {
        e.preventDefault();
        const selectedFile = e.target.files[0];
        if (Math.round(selectedFile.size / 1000000) > 5) {
            message.error("Image size must be less than 5 MB");
        } else {
            setFile(selectedFile);
        }
    };

    const onFileUpload = (e) => {
        e.preventDefault();
        const reader = new FileReader();
        reader.onloadend = () => {
            setPreviewURL(reader.result);
        };
        reader.readAsDataURL(file);
        setEditProfile(false);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const User = new FormData();
        User.append('name', name);
        User.append('email', email);
        User.append('password', password);
        User.append('password_confirmation', passwordConfirmation);
        if (file) {
            User.append('avatar', file, file.name);
        }

        updateuser(User).then((res) => {
            if (res.errorStatus) {
                setErrors(res.data);
            } else {
                setModal(false);
                message.success(res.data, 3, () => {
                    window.location.reload();
                });
            }
        });
    };

    const handleEditProfile = () => {
        setEditProfile(!editProfile);
        popoverRef.current.props.onPopupVisibleChange(false);
    };

    const handleRemoveProfile = () => {
        setFile('');
        setPreviewURL(addprofile);
        popoverRef.current.props.onPopupVisibleChange(false);
    };

    return (
        <div>
            <a onClick={toggle} style={{ cursor: 'pointer' }}>
                Account
            </a>
            <Modal
                isOpen={modal}
                toggle={toggle}
                onOpened={getMemberDetail}
                id="register_container"
                className="accountmodal"
            >
                <ModalHeader toggle={toggle} className="border-0"></ModalHeader>
                <ModalBody>
                    <div className="col-md-8 mt-5 mx-auto">
                        <form noValidate onSubmit={handleSubmit}>
                            <div className="previewComponent mt-5 mb-5">
                                {!editProfile ? (
                                    <Popover
                                        ref={popoverRef}
                                        content={
                                            <div>
                                                <div
                                                    className="cursor_pointer mb-1"
                                                    onClick={handleEditProfile}
                                                >
                                                    Edit
                                                </div>
                                                {previewURL !== addprofile && (
                                                    <Popconfirm
                                                        title="Are you sure you want to remove your current avatar?"
                                                        okText="Yes"
                                                        cancelText="No"
                                                        onConfirm={handleRemoveProfile}
                                                    >
                                                        <div className="cursor_pointer mb-1">
                                                            Remove
                                                        </div>
                                                    </Popconfirm>
                                                )}
                                            </div>
                                        }
                                        trigger="click"
                                    >
                                        <div
                                            className="cursor_pointer profile_avtar fileInputContainer position-relative text-center mx-auto"
                                            style={{
                                                backgroundImage: `url(${previewURL})`,
                                                width: 100,
                                                height: 100,
                                            }}
                                        >
                                            {previewURL !== addprofile && (
                                                <img
                                                    src={editprofile}
                                                    className="accountediticon position-absolute"
                                                    height="20"
                                                    width="20"
                                                    alt="Edit"
                                                />
                                            )}
                                        </div>
                                    </Popover>
                                ) : (
                                    <div>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={handleImageChange}
                                        />
                                        <button onClick={onFileUpload}>Upload</button>
                                        <button onClick={() => setEditProfile(false)}>Cancel</button>
                                    </div>
                                )}
                            </div>
                            <h1 className="h3 mb-3 font-weight-normal text-uppercase text-center mb-5">
                                <span className="title color-white">
                                    Edit Account <hr />
                                </span>
                            </h1>
                            <div className="form-group position-relative">
                                <input
                                    type="text"
                                    className="form-control"
                                    name="name"
                                    placeholder="Name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                />
                                {errors.name && <label className="error">{errors.name}</label>}
                            </div>
                            <div className="form-group position-relative">
                                <input
                                    type="email"
                                    className="form-control"
                                    name="email"
                                    placeholder="Email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                />
                                {errors.email && <label className="error">{errors.email}</label>}
                            </div>
                            <div className="form-group position-relative">
                                <input
                                    type="password"
                                    className="form-control"
                                    name="password"
                                    placeholder="Password"
                                    onChange={(e) => setPassword(e.target.value)}
                                />
                            </div>
                            <div className="form-group position-relative">
                                <input
                                    type="password"
                                    className="form-control"
                                    name="password_confirmation"
                                    placeholder="Confirm Password"
                                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                                />
                                {errors.password && (
                                    <label className="error">{errors.password}</label>
                                )}
                            </div>
                            <div className="text-center mt-5 mb-5">
                                <button type="submit">
                                    <img src={Submit} width="50" alt="Submit" />
                                </button>
                            </div>
                        </form>
                    </div>
                </ModalBody>
            </Modal>
        </div>
    );
};

export default AccountModal;