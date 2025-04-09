import activeIcon from '../../../public/images/icon_active.png'
import completeIcon from '../../../public/images/icon_complete.png'
import reviewIcon from '../../../public/images/icon_review.png'
import onHoldIcon from '../../../public/images/icon_hold.png'
import React from "react";
import priority1 from "../../../public/images/icon_flag_red.png";
import priority2 from "../../../public/images/icon_flag_yellow.png";
import priority3 from "../../../public/images/icon_flag_blue.png";
import priority4 from "../../../public/images/icon_flag_grey.png";
import attachmentIcon from "../../../public/images/icon_function_attachment.png"

export const ATTACHMENT_IMAGE = attachmentIcon;

export const colorOptions = [
    {
        value: '000000',
        name: "Black",
        label: (
            <div className="demo-option-label-item position-relative">
        <span
            className="color-round mr-2"
            style={{backgroundColor: '#000000'}}
        >
          &nbsp;
        </span><span className={"ml-3"}>Black</span>
            </div>
        ),
    },
    {
        value: 'ff0000',
        name: "Red",
        label: (
            <div className="demo-option-label-item position-relative">
        <span
            className="color-round mr-2"
            style={{backgroundColor: '#ff0000'}}
        >
          &nbsp;
        </span><span className={"ml-3"}>Red</span>
            </div>
        ),
    },
    {
        value: 'ffff00',
        name: "Yellow",
        label: (
            <div className="demo-option-label-item position-relative">
        <span
            className="color-round mr-2"
            style={{backgroundColor: '#ffff00'}}
        >
          &nbsp;
        </span>
                <span className={"ml-3"}>Yellow</span>
            </div>
        ),
    },
    {
        value: 'ffc0cb',
        name: "Pink",
        label: (
            <div className="demo-option-label-item position-relative">
        <span
            className="color-round mr-2"
            style={{backgroundColor: '#ffc0cb'}}
        >
          &nbsp;
        </span><span className={"ml-3"}>Pink</span>
            </div>
        ),
    },
    {
        value: 'FFA500',
        name: "Orange",
        label: (
            <div className="demo-option-label-item position-relative">
        <span
            className="color-round mr-2"
            style={{backgroundColor: '#FFA500'}}
        >
          &nbsp;
        </span><span className={"ml-3"}>Orange</span>
            </div>
        ),
    },
    {
        value: '0000ff',
        name: "Blue",
        label: (
            <div className="demo-option-label-item position-relative">
        <span
            className="color-round mr-2"
            style={{backgroundColor: '#0000ff'}}
        >
          &nbsp;
        </span><span className={"ml-3"}>Blue</span>
            </div>
        ),
    },
]

export const statusOptions = [
    {
        value: 1,
        name: "Active",
        label: (
            <div className="demo-option-label-item">
                <img src={activeIcon} alt="Active" className="mr-2"/>
                Active
            </div>
        ),
    },
    {
        value: 3,
        name: "Complete",
        label: (
            <div className="demo-option-label-item">
                <img src={completeIcon} alt="Complete" className="mr-2"/>
                Complete
            </div>
        ),
    },
    {
        value: 4,
        name: "Review",
        label: (
            <div className="demo-option-label-item">
                <img src={reviewIcon} alt="Review" className="mr-2"/>
                Review
            </div>
        ),
    },
    {
        value: 5,
        name: "On-hold",
        label: (
            <div className="demo-option-label-item">
                <img src={onHoldIcon} alt="On-hold" className="mr-2"/>
                On-hold
            </div>
        ),
    },
]

export const priorityFlags = {
    "1": {
        value: '1',
        label: 'Priority 1',
        image: priority1
    },
    "2": {
        value: '2',
        label: 'Priority 2',
        image: priority2
    },
    "3": {
        value: '3',
        label: 'Priority 3',
        image: priority3
    },
    "4": {
        value: '4',
        label: 'Priority 4',
        image: priority4
    }
}

export const statusValue = {
    "1": {
        value: '1',
        name: "Active"
    },

    "3": {
        value: '3',
        name: "Complete"
    },
    "4": {
        value: '4',
        name: "Review"
    },
    "5": {
        value: '5',
        name: "On-hold"
    }
}

export const reminderOptions = ['None', 'At time of event', '5 minutes before', '10 minutes before', '15 minutes before', '30 minutes before', '1 hour before', '2 hours before', '3 hours before', '1 day before', '2 days before', '3 days before', '1 week before'];
