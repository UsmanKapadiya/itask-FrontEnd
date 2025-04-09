import React, { useState, useEffect } from "react";

const HomeComponent = (OriginalComponent) => {
    const HomeComponentWrapper = (props) => {
        const [section, setSection] = useState(
            localStorage.getItem("section") ? atob(localStorage.getItem("section")) : ""
        );
        const [value, setValue] = useState(
            localStorage.getItem("value") ? atob(localStorage.getItem("value")) : ""
        );
        const [isEditView, setIsEditView] = useState(0);
        const [editProjectId, setEditProjectId] = useState("");
        const [inboxCount, setInboxCount] = useState(0);

        // Fetch inbox count on component mount
        useEffect(() => {
            updateInboxCount();
        }, []);

        // Function to update the selected section
        const updateSelectedSection = (property, value) => {
            setSection(property);
            setValue(value);

            if (property !== "search") {
                localStorage.setItem("section", btoa(property));
                localStorage.setItem("value", btoa(value));
            }
        };

        // Function to fetch and update the inbox count
        const updateInboxCount = () => {
            fetch("/inbox-task-count")
                .then((res) => res.json())
                .then(
                    (result) => {
                        setInboxCount(result.count);
                    },
                    (error) => {
                        console.error("Error fetching inbox count:", error);
                    }
                );
        };

        // Function to update the edit view state
        const updateEditView = (isEdit, projectId) => {
            setIsEditView(isEdit);
            setEditProjectId(projectId);
        };

        return (
            <OriginalComponent
                updateSelection={updateSelectedSection}
                updateEditView={updateEditView}
                updateInboxCount={updateInboxCount}
                section={section}
                value={value}
                isEditView={isEditView}
                editProjectId={editProjectId}
                inboxCount={inboxCount}
                {...props}
            />
        );
    };

    return HomeComponentWrapper;
};

export default HomeComponent;