import React, { useState, useRef } from 'react';
import dotImage from '../../../public/images/icon_left_more.png';
import editImage from '../../../public/images/icon_function_edit.png';
import deleteImage from '../../../public/images/icon_function_delete.png';
import { Popover } from 'antd';

const Section = (props) => {
    const [open, setOpen] = useState(false);
    const [className, setClassName] = useState('accordion-content accordion-close');
    const [headingClassName, setHeadingClassName] = useState('accordion-heading');
    const [content, setContent] = useState('../../images/icon_left_dropleft.png');
    const popoverRef = useRef(null);

    const handleClick = () => {
        if (open) {
            setOpen(false);
            setClassName('accordion-content accordion-close');
            setHeadingClassName('accordion-heading');
            setContent('../../images/icon_left_dropleft.png');
        } else {
            setOpen(true);
            setClassName('accordion-content accordion-open');
            setHeadingClassName('accordion-heading clicked');
            setContent('../../images/icon_left_dropdown.png');
        }
    };

    const project = props.project;
    const options = (
        <div>
            <a
                className="d-flex align-items-center mb-1"
                onClick={() => {
                    props.updateEditView(1, project.id);
                    popoverRef.current.props.onPopupVisibleChange(false);
                }}
            >
                <img src={editImage} className="mr-2" alt="Edit" />
                Edit project
            </a>
            <a
                className="d-flex align-items-center mb-1"
                onClick={() => {
                    props.deleteProjectTask(project.id, project.type, popoverRef);
                }}
            >
                <img src={deleteImage} className="mr-2" alt="Delete" />
                Delete project
            </a>
        </div>
    );

    return (
        <div>
            {project.child_projects.length === 0 ? (
                <div className="headingClassName pl-3">
                    <span className="mr-2">
                        <i
                            className="fas fa-circle"
                            style={{
                                fontSize: 'xx-small',
                                color: `#${project.color}`,
                            }}
                        ></i>
                    </span>
                    <span
                        className="project-name"
                        title={project.name}
                        onClick={() => {
                            props.updateSelection('project', project.id);
                        }}
                    >
                        {project.name}
                    </span>
                    {!props.is_completed && (
                        <span className="total-count">{project.no_of_tasks}</span>
                    )}
                    {project.is_creator_of_project && (
                        <Popover content={options} trigger="click" ref={popoverRef}>
                            <span className="cursor_pointer optionsDot float-right d-inline">
                                <img src={dotImage} alt="Options" />
                            </span>
                        </Popover>
                    )}
                </div>
            ) : (
                <div className="arrow-parent-accordion parent-accordion">
                    <div className="headingClassName d-flex align-items-center pl-3">
                        <span className="mr-2">
                            <i
                                className="fas fa-circle"
                                style={{
                                    fontSize: 'xx-small',
                                    color: `#${project.color}`,
                                }}
                            ></i>
                        </span>
                        <span
                            className="project-name"
                            title={project.name}
                            onClick={() => {
                                props.updateSelection('project', project.id);
                            }}
                        >
                            {project.name}
                        </span>
                        {!props.is_completed && (
                            <span className="total-count">{project.no_of_tasks}</span>
                        )}
                        <span
                            className={
                                project.is_creator_of_project
                                    ? 'ml-auto mr-4'
                                    : 'ml-auto mr-5'
                            }
                            onClick={handleClick}
                        >
                            <img src={content} alt="Project" style={{ cursor: 'pointer' }} />
                        </span>
                        {project.is_creator_of_project && (
                            <Popover content={options} trigger="click" ref={popoverRef}>
                                <span className="cursor_pointer optionsDot">
                                    <img src={dotImage} alt="Options" />
                                </span>
                            </Popover>
                        )}
                    </div>
                    <div className={className}>
                        {project.child_projects.map((childProject) => (
                            <Section
                                project={childProject}
                                key={childProject.id}
                                updateSelection={props.updateSelection}
                                deleteProjectTask={props.deleteProjectTask}
                                updateEditView={props.updateEditView}
                                is_completed={props.is_completed}
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default Section;