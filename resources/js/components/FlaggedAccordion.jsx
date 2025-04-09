import React, { useState } from 'react';
import flagImage from "../../../public/images/icon_left_flag.png";
import * as options from './Constants';

const priorityFlags = options.priorityFlags;

const FlaggedAccordion = ({ updateSelection }) => {
    const [isOpen, setIsOpen] = useState(false);

    const handleClick = () => {
        setIsOpen(!isOpen);
    };

    return (
        <div className="parent-accordion">
            {/* <div className="cursor_pointer nav-link" onClick={handleClick}>
                <img src={flagImage} alt="Flagged" className="mr-2 ml-3 " />
                <span className='ps-2'>Flagged</span>
                <span className="float-right ps-5">
                    <img
                        src={isOpen ? "../../images/icon_left_dropdown.png" : "../../images/icon_left_dropleft.png"}
                        alt="Flag"
                    />
                </span>
            </div> */}
            <div className="cursor_pointer nav-link d-flex align-items-center justify-content-between" onClick={handleClick}>
                <div className="d-flex align-items-center">
                    <img src={flagImage} alt="Flagged" className="mr-2 ml-3" />
                    <span className="ps-2">Flagged</span>
                </div>
                <div>
                    <img
                        src={isOpen ? "../../images/icon_left_dropdown.png" : "../../images/icon_left_dropleft.png"}
                        alt="Arrow"
                    />
                </div>
            </div>
            <div className={isOpen ? "accordion-content accordion-open" : "accordion-content accordion-close"}>
                <ul className="flaglist">
                    {Object.keys(priorityFlags).map((f) => (
                        <li key={f}>
                            <a href="#">
                                <span className="d-inline-block mr-2">
                                    <img src={priorityFlags[f].image} className="flag" height="30" />
                                </span>
                                <span onClick={() => updateSelection("flag", priorityFlags[f].value)}>
                                    {priorityFlags[f].label}
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
};

export default FlaggedAccordion;