import React from 'react';
import tagIcon from "../../../public/images/icon_left_tag.png";
import memberIcon from "../../../public/images/icon_add_assign.png";
import reminderIcon from "../../../public/images/icon_function_reminder.png";
import attachmentIcon from "../../../public/images/icon_function_attachment.png";
import commentIcon from "../../../public/images/icon_function_comment.png";
import projectIcon from "../../../public/images/icon_project.png";

const DisplayTag = (props) => {
    const { type, data } = props;

    let icon = "";
    if (type === "tag") icon = tagIcon;
    else if (type === "member") icon = memberIcon;
    else if (type === "reminder") icon = reminderIcon;
    else if (type === "attachment") icon = attachmentIcon;
    else if (type === "comment") icon = commentIcon;
    else if (type === "project") icon = projectIcon;

    return (
        <li className="tag">
            <img
                src={icon}
                alt="Indicator"
                className="mr-1 cursor_pointer"
                height="16"
            />
            {data.link !== undefined ? (
                <span
                    className="tag-title cursor_pointer"
                    onClick={() => {
                        window.open(data.link, '_blank');
                    }}
                >
                    {data.data}
                </span>
            ) : (
                <span className="tag-title">{data.data}</span>
            )}
            <span className="ml-1 cursor_pointer" onClick={data.removeFunction}>
                <i className="fas fa-times"></i>
            </span>
        </li>
    );
};

export default DisplayTag;