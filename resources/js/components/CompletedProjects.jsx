import React, { useState } from "react";
import completeImage from "../../../public/images/icon_left_completed.png";
import ProjectAccordion from "./ProjectAccordion";
import projectImage from "../../../public/images/icon_left_projects.png";
import AddProjectModal from "./AddProjectModal";

const ProjectListAccordion = ({
  isUpdateProjects,
  is_completed,
  updateSelection,
  deleteProjectTask,
  updateEditView,
  updateProject,
}) => {
  const [open, setOpen] = useState(false);

  const handleClick = () => {
    setOpen(!open);
  };

  return (
    <div className="parent-accordion">
      <div className={"cursor_pointer"} onClick={handleClick}>
        {is_completed ? (
          <div className="nav-link d-flex align-items-center justify-content-between">
          <div className="d-flex align-items-center">
              <img src={completeImage} alt="completed" className="me-2" />
              <span className="font-weight-bold">
                  Completed Projects
              </span>
          </div>
          <div className="d-flex align-items-center">
              <img
                  src={
                      open
                          ? "../../images/icon_left_dropdown.png"
                          : "../../images/icon_left_dropleft.png"
                  }
                  alt="Arrow"
              />
          </div>
      </div>
        ) : (
          <div className="nav-link d-flex align-items-center justify-content-between">
            <div className="d-flex align-items-center">
              <img alt="Projects" src={projectImage} className="mr-2 ml-3" />
              <span className="ps-2 font-weight-bold">Projects</span>
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
                <AddProjectModal isEdit={0} />
              </span>
            </div>
          </div>
        )}
      </div>
      <div
        className={`accordion-content ${open ? "accordion-open" : "accordion-close"
          }`}
      >
        <div className={"mt-2"}>
          <ProjectAccordion
            isUpdateProjects={isUpdateProjects}
            is_completed={is_completed}
            updateSelection={updateSelection}
            deleteProjectTask={deleteProjectTask}
            updateEditView={updateEditView}
            updateProject={updateProject}
          />
        </div>
      </div>
    </div>
  );
};

export default ProjectListAccordion;