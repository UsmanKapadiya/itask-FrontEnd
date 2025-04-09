import React, { useState, useEffect, useRef } from 'react';
import AddTagModal from "./AddTagModal";
import EditTagModal from "./EditTagModal";
import tagImage from "../../../public/images/icon_left_tag.png";
import dotImage from '../../../public/images/icon_left_more.png';
import deleteImage from '../../../public/images/icon_function_delete.png';
import { message, Popover } from 'antd';
import { deleteTag } from './FunctionCalls';
import { confirmAlert } from "react-confirm-alert";

const TagsAccordion = ({ updateSelection }) => {
    const [open, setOpen] = useState(false);
    const [tags, setTags] = useState([]);
    const [individualTag, setIndividualTag] = useState('');
    const popoverRef = useRef(null);

    useEffect(() => {
        fetchTagsData();
    }, []);

    const fetchTagsData = () => {
        fetch("/member-tags")
            .then((res) => res.json())
            .then(
                (result) => {
                    setTags(result);
                },
                (error) => {
                    message.error("Failed to fetch tags");
                }
            );
    };

    const handleClick = () => {
        setOpen(!open);
    };

    const handleDelete = (e) => {
        popoverRef.current.props.onPopupVisibleChange(false);
        const tagId = e.target.getAttribute("datavalue");
        setIndividualTag(tagId);

        confirmAlert({
            title: 'Delete tag',
            message: 'Are you sure you want to delete?',
            buttons: [
                {
                    label: 'Yes',
                    onClick: () => {
                        deleteTag({ tag_id: tagId }).then((res) => {
                            if (res.errorStatus) {
                                message.error(res.data);
                            } else {
                                message.success(res.data);
                                fetchTagsData();
                                updateSelection("inbox", 1);
                            }
                        });
                    },
                },
                {
                    label: 'No',
                },
            ],
        });
    };

    return (
        <div className="parent-accordion">
            {/* <div className="cursor_pointer" onClick={handleClick}>
                <img src={tagImage} alt="Tags" className="me-2 ml-3" />
                <span className="color-black font-weight-bold">Tags</span>
                <span className="ps-5 float-right">
                    <img
                        src={open ? "../../images/icon_left_dropdown.png" : "../../images/icon_left_dropleft.png"}
                        alt="Tags"
                    />
                </span>
                <span className="color-black mr-4 float-right d-inline" id="modal">
                    <AddTagModal refreshData={fetchTagsData} />
                </span>
            </div> */}
            <div className="nav-link d-flex align-items-center justify-content-between" onClick={handleClick}>
                <div className="d-flex align-items-center">
                    <img src={tagImage} alt="Tags" className="me-2" />
                    <span className="me-4 font-weight-bold">Tags</span>
                </div>
                <div className="d-flex align-items-center">
                    <img
                        src={
                            open
                                ? "../../images/icon_left_dropdown.png"
                                : "../../images/icon_left_dropleft.png"
                        }
                        alt="Arrow"
                        className="me-3"
                    />
                    <span id="modal">
                        <AddTagModal refreshData={fetchTagsData} />
                    </span>
                </div>
            </div>
            <div className={open ? "accordion-content accordion-open" : "accordion-content accordion-close"}>
                <div className="pl-4">
                    {tags.map((individual) => (
                        <div key={individual.id} className="headingClassName pl-3">
                            <span
                                className="project-name"
                                title={individual.name}
                                onClick={() => updateSelection("list_by_tag", individual.id)}
                            >
                                {individual.name}
                            </span>
                            <Popover
                                content={
                                    <div>
                                        <span className="d-flex align-items-center mb-1">
                                            <EditTagModal
                                                popRef={popoverRef}
                                                refreshData={fetchTagsData}
                                                name={individual.name}
                                                id={individual.id}
                                            />
                                        </span>
                                        <a
                                            className="d-flex align-items-center mb-1"
                                            datavalue={individual.id}
                                            onClick={handleDelete}
                                        >
                                            <img src={deleteImage} className="mr-2" alt="Delete" />
                                            Delete tag
                                        </a>
                                    </div>
                                }
                                trigger="click"
                                ref={popoverRef}
                            >
                                <span className="cursor_pointer optionsDot float-right d-inline mr-4">
                                    <img src={dotImage} alt="Options" />
                                </span>
                            </Popover>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default TagsAccordion;