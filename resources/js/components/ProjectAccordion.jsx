import React, { useState, useEffect } from "react";
import axios from "axios";
import Section from "./AccordionSection";

const ProjectAccordion = ({
  isUpdateProjects,
  is_completed,
  updateProject,
  deleteProjectTask,
  updateSelection,
  updateEditView,
}) => {
  const [projects, setProjects] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const getProject = () => {
    axios
      .post("/projects", { is_completed })
      .then((res) => {
        setProjects(res.data);
        setLoading(false);
        setError(null);
      })
      .catch(() => {
        setLoading(false);
        setError(true);
      });
    updateProject(0);
  };

  useEffect(() => {
    getProject();
  }, []);

  useEffect(() => {
    if (isUpdateProjects) {
      getProject();
    }
  }, [isUpdateProjects]);

  const renderLoading = () => (
    <div className="accordion-container">
      <h1 className="error">Loading...</h1>
    </div>
  );

  const renderError = () => (
    <div>
      Something went wrong, Will be right back.
    </div>
  );

  const renderPosts = () => {
    if (error) {
      return renderError();
    }

    return projects && projects.length > 0 ? (
      <div className={"border-top projects-container"}>
        <div className="accordion-container pl-4">
          {projects.map((project) => (
            <Section
              project={project}
              key={project.id}
              deleteProjectTask={deleteProjectTask}
              updateSelection={updateSelection}
              updateEditView={updateEditView}
              is_completed={is_completed}
            />
          ))}
        </div>
      </div>
    ) : (
      ""
    );
  };

  return <div className={"pt-1"}>{loading ? renderLoading() : renderPosts()}</div>;
};

export default ProjectAccordion;